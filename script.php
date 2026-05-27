<?php
require_once __DIR__ . "/vendor/autoload.php";
$config = require __DIR__ . "/config.php";
$payload = require __DIR__ . "/payload.php";

use Kenal\M4api\Api\M4ApiClient;
use Kenal\M4api\App;
use Kenal\M4api\Validator\AppRunValidator;

$apiClient = new M4ApiClient($config['base_url']);
$validator = new AppRunValidator();

$app = new App($apiClient, $validator);
$exitCode = $app->run($config, $payload);

exit($exitCode);