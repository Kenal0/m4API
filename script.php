<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use GuzzleHttp\Client;
use Kenal\M4api\Api\ApiClient;
use Kenal\M4api\App;
use Kenal\M4api\Auth\AuthManager;
use Kenal\M4api\Validator\AppRunValidator;

$config = require __DIR__ . '/config.php';
$payload = require __DIR__ . '/payload.php';

$guzzleClient = new Client([
    'http_errors' => false,
    'timeout' => 30,
    'connect_timeout' => 5,
]);

$validator = new AppRunValidator();
$authManager = new AuthManager($guzzleClient, $config['base_url']);
$apiClient = new ApiClient($guzzleClient, $authManager);

$app = new App($apiClient, $validator);
$exitCode = $app->run($config, $payload);

exit($exitCode);
