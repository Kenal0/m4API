<?php

declare(strict_types=1);

namespace Kenal\M4api\Api;

use GuzzleHttp\Client;
use Exception;
use Kenal\M4api\Auth\AuthManager;
use Kenal\M4api\Task\TaskService;
use Kenal\M4api\Transport\JsonRpcClient;
use Kenal\M4api\Transport\StorageClient;

class ApiClient implements TaskSystemInterface
{
    private Client $httpClient;
    private AuthManager $authManager;
    private ?string $token = null;
    private ?string $sdApiUrl = null;
    private ?string $storageApiUrl = null;
    private ?JsonRpcClient $jsonRpcClient = null;
    private ?StorageClient $storageClient = null;
    private ?TaskService $taskService = null;


    public function __construct(Client $httpClient, AuthManager $authManager)
    {
        $this->httpClient = $httpClient;
        $this->authManager = $authManager;
    }

    public function login(string $username, string $password): void
    {
        $this->authManager->login($username, $password);
        $this->token = $this->authManager->getToken();
        $this->sdApiUrl = $this->authManager->getSdApiUrl();
        $this->storageApiUrl = $this->authManager->getStorageApiUrl();
    }

    public function getSdApiUrl(): string
    {
        if (empty($this->sdApiUrl)) {
            throw new \Exception('Сервис SD не найден в списке доступных сервисов API.');
        }

        return $this->sdApiUrl;
    }

    public function getTasks(int $days = 3): array
    {
        return $this->taskService()->getTasks($days);
    }

    public function getTaskDetails(int $taskId): array
    {
        return $this->taskService()->getTaskDetails($taskId);
    }

    public function uploadFile(string $filePath): string
    {
        $this->validateToken();
        $guid = $this->storageClient()->uploadFile($filePath);
        return $guid;
    }

    public function addTaskAttach(int $taskId, array $guids): bool
    {
        return $this->taskService()->addTaskAttach($taskId, $guids);
    }

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool
    {
        return $this->taskService()->addTaskComment($taskId, $comment, $isPublic);
    }

    public function logout(): string
    {
        $this->validateToken();
        $result = $this->authManager->logout();
        $this->token = null;
        $this->sdApiUrl = null;
        $this->storageApiUrl = null;
        return $result;
    }

    private function validateToken(): void
    {
        if (empty($this->token)) {
            throw new Exception('Токен авторизации отсутствует.');
        }
    }

    private function taskService(): TaskService
    {
        if ($this->taskService === null) {
            $this->jsonRpcClient = new JsonRpcClient($this->httpClient, $this->token, $this->sdApiUrl);
            $this->taskService = new TaskService($this->jsonRpcClient);
        }

        return $this->taskService;
    }

    private function storageClient(): StorageClient
    {
        if ($this->storageClient === null) {
            $this->storageClient = new StorageClient($this->httpClient, $this->token, $this->storageApiUrl);
        }

        return $this->storageClient;
    }
}
