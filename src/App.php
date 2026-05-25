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

            $tickets = $this->api->getTicket(3);
            $amountOfTickets = count($tickets);

            echo 'Ответ от API успешно получен' . PHP_EOL;
            echo 'Количество найденных заявок: ' . $amountOfTickets . PHP_EOL;

            if ($amountOfTickets < 2) {
                echo 'Недостаточно заявок для выполнения тестового сценария';
                return 0;
        }

        echo '--- Структура данных (Отладка) ---' . PHP_EOL;

        print_r($tickets);
        return 0;
        } catch (Exception $c) {
            echo 'Ошибка исполнения: ' . $c->getMessage() . PHP_EOL;
            return 1;
        }
    }
}
