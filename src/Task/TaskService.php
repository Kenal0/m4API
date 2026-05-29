<?php

declare(strict_types=1);

namespace Kenal\M4api\Task;

use Exception;
use Kenal\M4api\Transport\JsonRpcClient;

class TaskService
{
    private const TYPE_ATTACH_DEFAULT = 5;
    private JsonRpcClient $jsonRpcClient;

    public function __construct(JsonRpcClient $jsonRpcClient)
    {
        $this->jsonRpcClient = $jsonRpcClient;
    }

    public function getTasks(int $days): array
    {
        $dateLimit = (new \DateTime())->modify("-{$days} days")->format('d.m.Y H:i:s');

        return $this->jsonRpcClient->sendRpcRequest('M4GetTasks', [
            'lastUpdate' => $dateLimit,
        ]);
    }

    public function getTaskDetails(int $taskId): array
    {
        $this->validateTaskId($taskId);

        return $this->jsonRpcClient->sendRpcRequest('M4GetTaskDetails', [
            'taskId' => $taskId,
        ]);

    }

    public function addTaskAttach(int $taskId, array $guids): bool
    {
        $this->validateTaskId($taskId);

        $filesParam = [];
        foreach ($guids as $guid) {
            if (empty($guid) || !is_string($guid)) {
                throw new Exception('Обнаружен некорректный guid файла в массиве вложений: ' . gettype($guid));
            }
            $filesParam[] = [
                'guid' => $guid,
                'typeAttachId' => self::TYPE_ATTACH_DEFAULT,
            ];
        }

        $this->jsonRpcClient->sendRpcCommand('M4AddTaskAttach', [
            'taskId' => $taskId,
            'files' => $filesParam,
        ]);

        return true;
    }

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool
    {
        $this->validateTaskId($taskId);

        $this->jsonRpcClient->sendRpcCommand('M4AddTaskComment', [
            'taskId' => $taskId,
            'comment' => $comment,
            'isPublic' => $isPublic,
        ]);

        return true;
    }

    private function validateTaskId(int $taskId): void
    {
        if ($taskId <= 0) {
            throw new Exception("Некорректный ID заявки: {$taskId}");
        }
    }
}