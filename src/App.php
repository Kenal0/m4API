<?php

namespace Kenal\M4api;

use Exception;
use Kenal\M4api\Api\M4ApiClient;

class App
{
    private M4ApiClient $api;

    public function __construct(M4ApiClient $api)
    {
        $this->api = $api;
    }

    public function run(array $config): int
    {
        try {
            echo "Попытка авторизации..." . PHP_EOL;

            $sdUrl = $this->api->login($config['login'], $config['password']);

            echo 'Успешная авторизация! URL сервиса SD: ' . $sdUrl . PHP_EOL;
            echo 'Запрос списка заявок за последние 3 дня.' . PHP_EOL;

            $tasks = $this->api->getTasks(150);
            $amountOfTasks = count($tasks);

            echo 'Ответ от API успешно получен' . PHP_EOL;
            print_r($tasks);
            echo 'Количество найденных заявок: ' . $amountOfTasks . PHP_EOL;

            if ($amountOfTasks < 2) {
                echo 'Недостаточно заявок для выполнения тестового сценария';
                return 0;
            }
            $secondTask = $tasks[1];
            $taskId =  $secondTask['taskId'];
            $detail = $this->api->getTaskDetails($taskId);

            echo 'taskId: ' . ($detail['taskId'] ?? 'N/A') . PHP_EOL;
            echo 'req: ' . ($detail['req'] ?? 'N/A') . PHP_EOL;
            echo 'caption: ' . ($detail['caption'] ?? 'N/A') . PHP_EOL;
            echo 'status: ' . $detail['status'] . ' ' . $detail['statusName'] . PHP_EOL;

            echo 'Загрузка файла(-ов) на сервер STORAGE...' . PHP_EOL;

            $file1 = __DIR__ . '/../image1.png';
            $file2 = __DIR__ . '/../image1.png';

            echo 'Загрузка первого файла (image1.png)...' . PHP_EOL;
            $guid1 = $this->api->uploadFile($file1);
            echo "Файл успешно загружен! GUID: {$guid1}" . PHP_EOL;

            echo 'Загрузка второго файла (image2.png)...' . PHP_EOL;
            $guid2 = $this->api->uploadFile($file2);
            echo "Файл успешно загружен! GUID: {$guid2}" . PHP_EOL;

            $uploadedGuids = [$guid1, $guid2];

            return 0;
        } catch (Exception $c) {
            echo 'Ошибка исполнения: ' . $c->getMessage() . PHP_EOL;
            return 1;
        }
    }
}
