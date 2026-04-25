<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$candidates = [
    realpath(__DIR__.'/../laravel-api'),
    realpath(__DIR__.'/../api'),
    realpath(__DIR__.'/..'),
];

$appRoot = null;

foreach ($candidates as $candidate) {
    if ($candidate !== false
        && is_file($candidate.'/artisan')
        && is_dir($candidate.'/bootstrap')
        && is_dir($candidate.'/vendor')) {
        $appRoot = $candidate;
        break;
    }
}

if ($appRoot === null) {
    http_response_code(500);
    exit('Laravel application root not found. Check your Hostinger folder layout.');
}

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
