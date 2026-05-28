<?php

declare(strict_types=1);

namespace Kenal\M4api\Transport;

use GuzzleHttp\Client;
use Exception;
use Kenal\M4api\Traits\JsonResponseTrait;

class JsonRpcClient
{
    use JsonResponseTrait;
    private Client $httpClient;
    private string $sdApiUrl;
    private string $token;

    public function __construct(Client $httpClient, string $token, string $sdApiUrl) {
        $this->httpClient = $httpClient;
        $this->token = $token;
        $this->sdApiUrl = $sdApiUrl;
    }

    public function sendRpcRequest(string $method, array $params = []): array
    {
        $result = $this->sendRpc($method, $params);

        if (!is_array($result)) {
            throw new Exception("Метод {$method} должен был вернуть массив, но вернул что-то другое.");
        }

        return $result;
    }

    public function sendRpcCommand(string $method, array $params = []): void
    {
        $result = $this->sendRpc($method, $params);

        if ($result !== true) {
            throw new Exception("Метод {$method} не выполнился успешно: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    }

    private function sendRpc(string $method, array $params = []): mixed
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

    private function validateToken(): void
    {
        if (empty($this->token)) {
            throw new Exception('Токен авторизации отсутствует.');
        }
    }
}