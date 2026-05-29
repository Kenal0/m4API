<?php

declare(strict_types=1);

namespace Kenal\M4api;

use Exception;
use Kenal\M4api\Api\TaskSystemInterface;
use Kenal\M4api\Validator\PayloadValidatorInterface;

class App
{
    private TaskSystemInterface $api;
    private PayloadValidatorInterface $validator;

    public function __construct(TaskSystemInterface $api, PayloadValidatorInterface $validator)
    {
        $this->api = $api;
        $this->validator = $validator;
    }

    public function run(array $config, array $payload): int
    {
        try {
            $this->validator->validatePayload($payload);
        } catch (Exception $e) {
            echo $e->getMessage();
            return 1;
        }

        $isLoggedIn = false;
        try {
            echo "Попытка авторизации..." . PHP_EOL;

            $this->api->login($config['login'], $config['password']);
            $isLoggedIn = true;
            $sdUrl = $this->api->getSdApiUrl();

            echo "Успешная авторизация! URL сервиса SD: {$sdUrl}" . PHP_EOL ;

            $amountOfDays = $payload['amountOfDays'];
            echo "Запрос заявок, измененных за указанный период (дней): {$amountOfDays}" . PHP_EOL;

            $tasks = $this->api->getTasks($amountOfDays);
            $amountOfTasks = count($tasks);

            echo 'Ответ от API успешно получен' . PHP_EOL;
            echo 'Количество найденных заявок: ' . $amountOfTasks . PHP_EOL;

            if ($amountOfTasks < 2) {
                echo 'Недостаточно заявок для выполнения тестового сценария' . PHP_EOL;
                return 0;
            }

            $secondTask = $tasks[1];
            $taskId = $secondTask['taskId'];
            $detail = $this->api->getTaskDetails($taskId);

            echo 'Информация о второй заявке в списке:' . PHP_EOL;
            echo 'taskId: ' . ($detail['taskId'] ?? 'N/A') . PHP_EOL;
            echo 'req: ' . ($detail['req'] ?? 'N/A') . PHP_EOL;
            echo 'caption: ' . ($detail['caption'] ?? 'N/A') . PHP_EOL;
            echo 'status: ' . ($detail['status']['id'] ?? $detail['status'] ?? 'N/A') . PHP_EOL;
            echo 'statusName: ' . ($detail['status']['name'] ?? $detail['statusName'] ?? 'N/A') . PHP_EOL;

            echo 'Загрузка файла(-ов) на сервер STORAGE...' . PHP_EOL;
            $uploadedGuids = $this->uploadFiles($payload['files']);
            echo 'Файлы отправлены на сервер' . PHP_EOL;

            echo "Прикрепляем файлы к заявке {$taskId}" . PHP_EOL;
            $this->api->addTaskAttach($taskId, $uploadedGuids);
            echo 'Файлы успешно прикреплены к заявке!' . PHP_EOL;

            echo "Добавляем текстовый комментарий к заявке {$taskId}" . PHP_EOL;
            $fio = $payload['fio'];
            $dateTime = date('Y-m-d H:i:s');
            $commentText = "Тестовый комментарий от кандидата: {$fio}, {$dateTime}";

            $this->api->addTaskComment($taskId, $commentText);
            echo 'Комментарий успешно добавлен в заявку!' . PHP_EOL;

            return 0;
        } catch (Exception $e) {
            echo 'Ошибка исполнения: ' . $e->getMessage() . PHP_EOL;
            return 1;
        } finally {
            if ($isLoggedIn) {
                try {
                    echo 'Выполняем выход из аккаунта' . PHP_EOL;
                    $logoutMessage = $this->api->logout();
                    echo "Ответ сервера: {$logoutMessage}" . PHP_EOL;
                } catch (Exception $logoutException) {
                    echo 'Не удалось выполнить выход из аккаунта: ' . $logoutException->getMessage() . PHP_EOL;
                }
            }
        }
    }

    private function uploadFiles(array $files): array
    {
        $uploadedGuids = [];
        $i = 1;
        foreach ($files as $file) {
            echo "Загрузка {$i} файла" . PHP_EOL;
            $guid = $this->api->uploadFile($file);
            $uploadedGuids[] = $guid;
            echo "Файл успешно загружен! GUID: {$guid}" . PHP_EOL;
            $i++;
        }

        return $uploadedGuids;
    }
}
