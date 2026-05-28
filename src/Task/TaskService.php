<?php

declare(strict_types=1);

namespace Kenal\M4api\Task;

use Exception;
use Kenal\M4api\Transport\JsonRpcClient;

class TaskService
{
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
        return $this->jsonRpcClient->sendRpcRequest('M4GetTaskDetails', [
            'taskId' => $taskId,
        ]);

    }

    public function addTaskAttach(int $taskId, array $guids): bool
    {
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

        $this->jsonRpcClient->sendRpcCommand('M4AddTaskAttach', [
            'taskId' => $taskId,
            'files' => $filesParam,
        ]);

        return true;
    }

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool
    {
        $this->jsonRpcClient->sendRpcCommand('M4AddTaskComment', [
            'taskId' => $taskId,
            'comment' => $comment,
            'isPublic' => $isPublic,
        ]);

        return true;
    }
}