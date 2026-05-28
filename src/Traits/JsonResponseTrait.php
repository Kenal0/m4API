<?php

declare(strict_types=1);

namespace Kenal\M4api\Traits;

use Exception;
use Psr\Http\Message\ResponseInterface;

trait JsonResponseTrait
{
    private function validateResponse(ResponseInterface $response, string $errorContext): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new Exception("{$errorContext} Код ответа: {$statusCode}");
        }
    }

    private function decodeJsonResponse(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка парсинга JSON: ' . json_last_error_msg());
        }
        if (!is_array($data)) {
            throw new Exception("Ответ API не является массивом JSON. Ответ: {$data}");
        }

        return $data;
    }
}