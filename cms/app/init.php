<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/installer.php';
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/vespers_text.php';
require_once __DIR__ . '/telegram.php';

foreach (glob(__DIR__ . '/../controllers/*.php') ?: [] as $controllerFile) {
    require_once $controllerFile;
}

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Явные параметры сессионной cookie: не полагаемся на php.ini хостинга
    // (по умолчанию HttpOnly и SameSite могут быть выключены).
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    if (!$https && strpos(strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')), 'https') === 0) {
        $https = true;
    }
    $current = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => (int)($current['lifetime'] ?? 0),
        // путь/домен не меняем: на хостинге они могут быть настроены под подкаталог
        'path' => (string)($current['path'] ?? '') !== '' ? (string)$current['path'] : '/',
        'domain' => (string)($current['domain'] ?? ''),
        'httponly' => true,
        'secure' => $https || !empty($current['secure']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

run_migrations();

// «Запомнить меня»: восстанавливаем сессию после миграций (нужны колонки v22)
if (CMS_INSTALLED) {
    Auth::resumeFromRemember();
}