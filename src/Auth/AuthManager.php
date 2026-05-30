<?php

declare(strict_types=1);

namespace Kenal\M4api\Auth;

use Exception;
use GuzzleHttp\Client;
use Kenal\M4api\Traits\JsonResponseTrait;

class AuthManager
{
    use JsonResponseTrait;

    private Client $httpClient;
    private string $authUrl;
    private ?string $username;
    private ?string $password;
    private ?string $token = null;
    private ?string $sdApiUrl = null;
    private ?string $storageApiUrl = null;

    public function __construct(Client $httpClient, string $authUrl, string $username, string $password)
    {
        $this->httpClient = $httpClient;
        $this->authUrl = $authUrl;
        $this->username = $username;
        $this->password = $password;
    }

    public function login(): void
    {
        $url = $this->authUrl . '/login_check';

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'username' => $this->username,
                'password' => $this->password,
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

    public function getToken(): string
    {
        if ($this->token === null) {
            throw new \Exception('Токен отсутствует.');
        }
        return $this->token;
    }

    public function getSdApiUrl(): string
    {
        if ($this->sdApiUrl === null) {
            throw new \Exception('SdApiUrl отсутствует.');
        }
        return $this->sdApiUrl;
    }

    public function getStorageApiUrl(): string
    {
        if ($this->storageApiUrl === null) {
            throw new \Exception('StorageApiUrl отсутствует.');
        }
        return $this->storageApiUrl;
    }

    public function logout(): string
    {
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

        $this->token = null;
        $this->sdApiUrl = null;
        $this->storageApiUrl = null;
        return $result['message'] ?? 'Успешный выход.';
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
    }
}
