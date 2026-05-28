<?php

declare(strict_types=1);

namespace Kenal\M4api\Api;

use GuzzleHttp\Client;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

class M4ApiClient implements TaskSystemInterface
{
    private Client $httpClient;
    private string $authUrl;
    private ?string $token = null;
    private ?string $sdApiUrl = null;
    private ?string $storageApiUrl = null;

    public function __construct(Client $httpClient, string $authUrl)
    {
        $this->authUrl = $authUrl;
        $this->httpClient = $httpClient;
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

        $this->validateResponse($response, 'Ошибка авторизации.');

        $responseData = $this->decodeJsonResponse($response);

        if (empty($responseData['token'])) {
            throw new Exception('Токен не найден в API.');
        }

        $this->token = $responseData['token'];

        if (is_array($responseData['services'] ?? null)) {
            $this->extractServiceUrls($responseData['services']);
        } else {
            throw new Exception('Массив сервисов в ответе API не найден.');
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

        $this->getSdServiceUrl();
        $this->validateStorageApiUrl();
    }

    public function getSdServiceUrl(): string
    {
        if (empty($this->sdApiUrl)) {
            throw new \Exception('Сервис SD не найден в списке доступных сервисов API.');
        }

        return $this->sdApiUrl;
    }

    public function getTasks(int $days = 3): array
    {
        $dateLimit = (new \DateTime())->modify("-{$days} days")->format('d.m.Y H:i:s');

        return $this->sendRpcRequest('M4GetTasks', [
            'lastUpdate' => $dateLimit,
        ]);
    }

    public function getTaskDetails(int $taskId): array
    {
        $this->validateTaskId($taskId);

        $result = $this->sendRpcRequest('M4GetTaskDetails', [
            'taskId' => $taskId,
        ]);

        return $result;
    }

    public function uploadFile(string $filePath): string
    {
        $this->validateStorageApiUrl();
        $this->validateToken();

        $url = $this->storageApiUrl . '/putfile.php';

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ]
            ]
        ]);

        $this->validateResponse($response, 'Ошибка сети при загрузке файла.');

        $responseData = $this->decodeJsonResponse($response);
        $guid = $responseData['result']['guid'] ?? null;

        if (empty($guid)) {
            throw new Exception('Сервер не вернул guid файл. Ответ: ' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
        }

        return $guid;
    }

    public function addTaskAttach(int $taskId, array $guids): bool
    {
        $this->validateTaskId($taskId);

        if (empty($guids)) {
            throw new Exception("Массив guids файлов пуст. Нечего прикреплять к заявке {$taskId}");
        }

        $filesParam = [];
        foreach ($guids as $guid) {
            if (empty($guid) || !is_string($guid)) {
                throw new Exception('Обнаружен некорректный guid файла в массиве вложений: ' . gettype($guid));
            }
            $filesParam[] = [
                'guid' => $guid,
                'typeAttachId' => 5,
            ];
        }

        $this->sendRpcCommand('M4AddTaskAttach', [
            'taskId' => $taskId,
            'files' => $filesParam,
        ]);

        return true;
    }

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool
    {
        $this->validateTaskId($taskId);

        $this->sendRpcCommand('M4AddTaskComment', [
            'taskId' => $taskId,
            'comment' => $comment,
            'isPublic' => $isPublic,
        ]);

        return true;
    }

    public function logout(): string
    {
        $this->validateToken();

        $response = $this->httpClient->request('POST', $this->authUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'json' => [
                'jsonrpc' => '2.0',
                'method' => 'logout',
                'id' => 1,
            ],
        ]);

        $this->validateResponse($response, 'Ошибка сети при выходе.');

        $responseData = $this->decodeJsonResponse($response);
        $result = $responseData['result'] ?? null;

        if (!isset($result['code']) || $result['code'] !== 200) {
            throw new Exception('Не удалось выйти: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $result['message'] ?? 'Успешный выход.';
    }

    private function executeRpc(string $method, array $params = []): mixed
    {
        $this->validateToken();

        $response = $this->httpClient->request('POST', $this->sdApiUrl, [
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

        $this->validateResponse($response, "Ошибка API при вызове {$method}.");

        $responseData = $this->decodeJsonResponse($response);

        if (isset($responseData['error'])) {
            throw new Exception("API вернул ошибку в методе {$method} :" . json_encode($responseData['error'], JSON_UNESCAPED_UNICODE));
        }

        return $responseData['result'] ?? null;
    }

    private function sendRpcRequest(string $method, array $params = []): array
    {
        $result = $this->executeRpc($method, $params);

        if (!is_array($result)) {
            throw new Exception("Метод {$method} должен был вернуть массив, но вернул что-то другое.");
        }

        return $result;
    }

    private function sendRpcCommand(string $method, array $params = []): void
    {
        $result = $this->executeRpc($method, $params);

        if ($result !== true) {
            throw new Exception("Метод {$method} не выполнился успешно: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    }

    private function validateResponse(ResponseInterface $response, string $errorContext): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new Exception("{$errorContext} Код ответа: {$statusCode}");
        }
    }

    private function validateStorageApiUrl(): void
    {
        if (empty($this->storageApiUrl)) {
            throw new Exception('URL сервиса STORAGE не установлен. Проверьте ответ авторизации.');
        }
    }

    private function validateToken(): void
    {
        if (empty($this->token)) {
            throw new Exception('Токен авторизации отсутствует.');
        }
    }

    private function validateTaskId(int $taskId): void
    {
        if ($taskId <= 0) {
            throw new Exception("Некорректный ID заявки: {$taskId}");
        }
    }

    private function decodeJsonResponse(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка парсинга JSON: ' . json_last_error_msg());
        }
        if (!is_array($data)) {
            throw new Exception("Ответ API не является массивом JSON. Ответ: {$data}");
        }

        return $data;
    }
}
