<?php

namespace Kenal\M4api\Api;

use GuzzleHttp\Client;

class M4ApiClient
{
    private Client $httpClient;
    private string $authUrl;
    private ?string $token = null;
    private ?string $sdApiUrl = null;

    public function __construct(string $authUrl)
    {
        $this->authUrl = $authUrl;
        $this->httpClient = new Client(['http_errors' => false]);
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

        if (!empty($data['services'])) {
            foreach ($data['services'] as $service) {
                if (($service['code'] ?? '') === 'SD') {
                    $this->sdApiUrl = $service['apiUrl'];
                    return $this->sdApiUrl;
                }
            }
        }

        throw new \Exception('Сервис SD не найден в списке доступных сервисов.');
    }

    public function sendRpcRequest(string $method, array $params = []): array
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
}
