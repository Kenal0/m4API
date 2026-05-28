<?php

declare(strict_types=1);

namespace Kenal\M4api\Transport;

use GuzzleHttp\Client;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Kenal\M4api\Traits\JsonResponseTrait;

class StorageClient
{
    use JsonResponseTrait;

    private Client $httpClient;
    private string $storageApiUrl;
    private string $token;

    public function __construct(Client $httpClient, string $token, string $storageApiUrl)
    {
        $this->httpClient = $httpClient;
        $this->storageApiUrl = $storageApiUrl;
        $this->token = $token;
    }

    public function uploadFile(string $filePath): string
    {
        $url = $this->storageApiUrl . '/putfile.php';

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ]
            ]
        ]);

        $this->validateResponse($response, 'Ошибка сети при загрузке файла.');

        $responseData = $this->decodeJsonResponse($response);
        $guid = $responseData['result']['guid'] ?? null;

        if (empty($guid)) {
            throw new Exception('Сервер не вернул guid файл. Ответ: ' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
        }

        return $guid;
    }
}
