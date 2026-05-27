<?php

namespace Kenal\M4api\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class M4ApiClient implements TaskSystemInterface
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
        ]);
    }

    public function login(string $username, string $password): void
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

        $data = json_decode((string) $response->getBody(), true);

        if (empty($data['token'])) {
            throw new \Exception('Токен не найден в API.');
        }

        $this->token = $data['token'];

        if (is_array($data['services'] ?? null)) {
            $this->extractServiceUrls($data['services']);
        } else {
            throw new \Exception('Список сервисов пуст или отсутствует в ответе авторизации.');
        }
    }

    private function extractServiceUrls(array $services): void
    {
        foreach ($services as $service) {
            $code = $service['code'] ?? null;

            switch ($code) {
                case 'SD':
                    $this->sdApiUrl = $service['apiUrl'] ?? null;
                    break;
                case 'STORAGE':
                    $this->storageApiUrl = $service['apiUrl'] ?? null;
                    break;
            }
        }

        if (empty($this->sdApiUrl)) {
            throw new \Exception('Сервис SD не найден в списке доступных сервисов API.');
        }
    }

    public function getSdServiceUrl(): string
    {
        if (empty($this->sdApiUrl)) {
            throw new \Exception('URL сервиса SD еще не получен. Сначала необходимо залогиниться.');
        }

        return $this->sdApiUrl;
    }

    /**
     * @throws \Exception
     */
    private function sendRpcRequest(string $method, array $params = []): array|bool
    {
        if (empty($this->token)) {
            throw new \Exception('Токен авторизации отсутствует');
        }

        $response = $this->httpClient->request('POST', $this->getSdServiceUrl(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
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

        $responseData = json_decode((string) $response->getBody(), true);

        if (isset($responseData['error'])) {
            throw new \Exception("API вернул ошибку в методе {$method} :" . json_encode($responseData['error'], JSON_UNESCAPED_UNICODE));
        }

        return $responseData['result'] ?? [];
    }

    private function sendRpcCommand (string $method, array $params = [])
    {
        $result = $this->sendRpcRequest($method, $params);

        if ($result !== true) {
            throw new \Exception("Метод {$method} вернул неожиданный ответ: " . json_encode($result), JSON_UNESCAPED_UNICODE);
        }
    }

    public function getTasks(int $days = 3): array
    {
        if ($days <= 0) {
            throw new \Exception("Количество дней должно быть больше нуля. Передано: {$days}");
        }

        $dateLimit = (new \DateTime())->modify("-{$days} days")->format('d.m.Y H:i:s');

        $result = $this->sendRpcRequest('M4GetTasks', [
            'lastUpdate' => $dateLimit,
        ]);

        if (!is_array($result)) {
            throw new \Exception('Метод M4GetTasks вернул некорректный тип данных вместо массива.');
        }

        return $result;
    }

    public function getTaskDetails(int $taskId): array
    {
        if ($taskId <= 0) {
            throw new \Exception("Следующий ID заявки является некорректным для получения деталей: {$taskId}");
        }

        $result = $this->sendRpcRequest('M4GetTaskDetails', [
            'taskId' => $taskId,
        ]);

        if (!is_array($result)) {
            throw new \Exception("Метод M4GetTaskDetails для заявки {$taskId} вернул некорректный ответ.");
        }

        return $result;
    }

    public function uploadFile(string $filePath): string
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

        $responseData = json_decode((string) $response->getBody(), true);
        $guid = $responseData['result']['guid'] ?? null;

        if (empty($guid)) {
            throw new \Exception('Сервер не вернул guid файл. Ответ: ' . json_encode($responseData), JSON_UNESCAPED_UNICODE);
        }

        return $guid;
    }

    public function addTaskAttach(int $taskId, array $guids): bool
    {
        if ($taskId <= 0) {
            throw new \Exception("Некорректный ID заявки для прикрепления файлов: {$taskId}");
        }
        if (empty($guids)) {
            throw new \Exception("Массив guids файлов пуст. Нечего прикреплять к заявке {$taskId}");
        }

        $filesParam = [];
        foreach ($guids as $guid) {
            if (empty($guid) || !is_string($guid)) {
                throw new \Exception('Обнаружен некорректный guid файла в массиве вложений: ' . gettype($guid));
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
            throw new \Exception('Сервер вернул неожиданный ответ при прикреплении файлов.' . json_encode($result), JSON_UNESCAPED_UNICODE);
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
