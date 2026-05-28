<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use GuzzleHttp\Client;
use Kenal\M4api\Api\M4ApiClient;
use Kenal\M4api\App;
use Kenal\M4api\Validator\AppRunValidator;

$config = require __DIR__ . "/config.php";
$payload = require __DIR__ . "/payload.php";

$guzzleClient = new Client([
    'http_errors' => false,
]);
$apiClient = new M4ApiClient($guzzleClient, $config['base_url']);
$validator = new AppRunValidator();

$app = new App($apiClient, $validator);
$exitCode = $app->run($config, $payload);

exit($exitCode);
