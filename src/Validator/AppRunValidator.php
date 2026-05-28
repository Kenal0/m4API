<?php

declare(strict_types=1);

namespace Kenal\M4api\Validator;

use Exception;

class AppRunValidator implements PayloadValidatorInterface
{
    public function validatePayload(array $payload): void
    {
        $errors = [];

        if (!is_string($payload['fio'] ?? null) || trim($payload['fio']) === '') {
            $errors[] = 'Поле "fio" обязателено для заполния и должен быть строкой';
        }

        if((int) ($payload['amountOfDays'] ?? 0) <= 0) {
            $errors[] = 'Параметр "amountOfDays" должен быть больше нуля.';
        }

        if (!is_array($payload['files'] ?? null)) {
            $errors[] = 'Параметр "files" должен быть массивом путей к файлам.';
        } else {
            foreach ($payload['files'] as $file) {
                if (!is_string($file) || !file_exists($file)) {
                    $errors[] = 'Файл не найден или неверно передан тип: ' . (is_string($file) ? basename($file) : gettype($file));
                }
            }
        }

        if (!empty($errors)) {
            $errorMessage = 'Ошибка валидации переданных данных внутри payload.php:' . PHP_EOL . implode(PHP_EOL, $errors);
            throw new Exception($errorMessage);
        }
    }
}
