<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\ApiController;

$apiController = new ApiController();
$apiController->adminBroadcast();

