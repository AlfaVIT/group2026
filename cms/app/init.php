<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/installer.php';

foreach (glob(__DIR__ . '/../controllers/*.php') ?: [] as $controllerFile) {
    require_once $controllerFile;
}

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

run_migrations();