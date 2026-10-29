<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/settings.php';
require_once __DIR__ . '/app/installer.php';
require_once __DIR__ . '/app/telegram.php';

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

if (!CMS_INSTALLED) {
    http_response_code(200);
    exit;
}

run_migrations();

if (!Telegram::configured()) {
    http_response_code(200);
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode((string)$raw, true);
if (!is_array($update)) {
    http_response_code(200);
    exit;
}

foreach (telegram_handle_update($update) as $reply) {
    Telegram::sendMessage($reply['chat_id'], $reply['text']);
}

http_response_code(200);