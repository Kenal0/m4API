<?php

namespace Kenal\M4api\Api;

interface TaskSystemInterface
{
    public function login(string $username, string $password): void;

    public function getSdServiceUrl(): string;

    public function getTasks(int $days = 3): array;

    public function getTaskDetails(int $taskId): array;

    public function uploadFile(string $filePath): string;

    public function addTaskAttach(int $taskId, array $guids): bool;

    public function addTaskComment(int $taskId, string $comment, bool $isPublic = true): bool;

    public function logout(): string;
}
