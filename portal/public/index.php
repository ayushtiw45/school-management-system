<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Works in both layouts: Hostinger (public_html + ../tds-portal) and the normal Laravel folder
$base = is_dir(__DIR__.'/../tds-portal') ? __DIR__.'/../tds-portal' : __DIR__.'/..';

// Not installed yet: open the installer
if (! file_exists($base.'/.env') && file_exists(__DIR__.'/install.php')) {
    header('Location: install.php');
    exit;
}

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
