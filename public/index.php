<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Not installed yet: open the installer
if (! file_exists(__DIR__.'/../.env') && file_exists(__DIR__.'/install.php')) {
    $root = rtrim(preg_replace('#/public$#', '', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    header('Location: '.$root.'/install.php');
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
