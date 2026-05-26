<?php

namespace Kenal\M4api\Api;

use GuzzleHttp\Client;

class M4ApiClient
{
    private Client $httpClient;
    private string $authUrl;
    private ?string $token = null;
    private ?string $sdApiUrl = null;
    private ?string $storageApiUrl = null;

    public function __construct(string $authUrl)
    {
        $this->authUrl = $authUrl;
        $this->httpClient = new Client([
            'http_errors' => false,
            'debug' => true,
        ]);
    }

    public function login(string $username, string $password)
    {
        $url = $this->authUrl . '/login_check';

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'username' => $username,
                'password' => $password,
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Ошибка авторизации. Код ответа: ' . $response->getStatusCode());
        }

        $data = json_decode($response->getBody(), true);

        if (empty($data['token'])) {
            throw new \Exception('Токен не найден в API.');
        }

        $this->token = $data['token'];
        echo "--- ДОСТУПНЫЕ СЕРВИСЫ И ИХ URL ---" . PHP_EOL;
        var_dump($data['services']);
        echo "---------------------------------" . PHP_EOL;

        if (!empty($data['services'])) {
            foreach ($data['services'] as $service) {
                if (($service['code'] ?? '') === 'SD') {
                    $this->sdApiUrl = $service['apiUrl'];
                    return $this->sdApiUrl;
                }
                if (($service['code'] ?? '') === 'STORAGE') {
                    $this->storageApiUrl = $service['apiUrl'];
                }
            }
        }

        throw new \Exception('Сервис SD не найден в списке доступных сервисов.');
    }

    public function sendRpcRequest(string $method, array $params = [])
    {
        if (empty($this->sdApiUrl)) {
            throw new \Exception('URL сервиса SD не установлен. Сначала выполните login().');
        }

        if (empty($this->token)) {
            throw new \Exception('Токен авторизации отсутствует');
        }

        $response = $this->httpClient->request('POST', $this->sdApiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Ошибка API при вызове {$method}. Код ответа " . $response->getStatusCode());
        }

        $responseData = json_decode($response->getBody(), true);

        if (isset($responseData['error'])) {
            throw new \Exception("API вернул ошибку в методе {$method}" . json_encode($responseData['error']));
        }

        return $responseData['result'] ?? [];
    }

    public function getTasks(int $days = 3): array
    {
        $dateLimit = (new \DateTime())->modify("-{$days} days")->format('d.m.Y H:i:s');

        return $this->sendRpcRequest('M4GetTasks', [
            'lastUpdate' => $dateLimit,
        ]);
    }

    public function getTaskDetails($taskId): array
    {
        return $this->sendRpcRequest('M4GetTaskDetails', [
            'taskId' => $taskId,
        ]);
    }

    public function uploadFile(string $filePath)
    {
        if (empty($this->storageApiUrl)) {
            throw new \Exception('URL сервиса STORAGE не установлен. Проверьте ответ авторизации.');
        }

        if (empty($this->token)) {
            throw new \Exception('Токен авторизации отсутствует.');
        }

        if (!file_exists($filePath)) {
            throw new \Exception("Файл не найден по пути: {$filePath}");
        }

        $url = $this->storageApiUrl . '/putfile.php';

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ]
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Ошибка при загрузке файла. Код ответа: ' . $response->getStatusCode());
        }

        $responseData = json_decode($response->getBody(), true);
        $guid = $responseData['result']['guid'] ?? null;

        if (empty($guid)) {
            throw new \Exception('Сервер не вернул guid файл. Ответ: ' . json_encode($responseData));
        }

        return $guid;
    }

    public function addTaskAttach(int $taskId, array $guids)
    {
        if (empty($taskId) || $taskId <= 0) {
            throw new \Exception("Некорректный ID заявки для прикрепления файлов: {$taskId}");
        }
        if (empty($guids)) {
            throw new \Exception("Массив guids файлов пуст. Нечего прикреплять к заявке {$taskId}");
        }

        $filesParam = [];
        foreach ($guids as $guid) {
            if (empty($guid) || !is_string($guid)) {
                throw new \Exception('Обнаружен  некорректный guid файла в массиве вложений' . gettype($guid));
            }
            $filesParam[] = [
                'guid' => $guid,
                'typeAttachId' => 5,
            ];
        }

        $result = $this->sendRpcRequest('M4AddTaskAttach', [
            'taskId' => $taskId,
            'files' => $filesParam,
        ]);

        if ($result !== true) {
            throw new \Exception('Сервер вернул неожиданный ответ при прикреплении файлов .' . json_encode($result));
        }

        return true;
    }

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool
    {
        if ($taskId <= 0) {
            throw new \Exception("Некорректный ID заявки для добавления комментария: {$taskId}");
        }

        if (empty(trim($comment))) {
            throw new \Exception('Текст комментария не может быть пустым');
        }

        $result = $this->sendRpcRequest('M4AddTaskComment', [
            'taskId' => $taskId,
            'comment' => $comment,
            'isPublic' => $isPublic,
        ]);

        if ($result !== true) {
            throw new \Exception('Сервер вернул неожиданный ответ при добавлении комментария :' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        return true;
    }

    public function logout(): string
    {
        $response = $this->httpClient->request('POST', $this->authUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'logout',
                'id' => 1,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Ошибка сети при выходе ' . $response->getStatusCode());
        }

        $responseData = json_decode((string)$response->getBody(), true);
        $result = $responseData['result'] ?? [];

        if (!isset($result['code']) || $result['code'] !== 200) {
            throw new \Exception('Не удалось выйти: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $result['message'] ?? 'Успешный выход.';
    }
}
