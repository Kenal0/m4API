<?php
require_once __DIR__ . "/vendor/autoload.php";
$config = require_once __DIR__ . "/config.php";
$payload = require_once __DIR__ . "/payload.php";

use Kenal\M4api\Api\M4ApiClient;
use Kenal\M4api\App;

$apiClient = new M4ApiClient($config['base_url']);

$app = new App($apiClient);
$exitCode = $app->run($config, $payload);

exit($exitCode);