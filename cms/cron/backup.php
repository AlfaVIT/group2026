<?php
/**
 * Отправка бекапа базы по расписанию из cron хостинга.
 *
 * Пример строки crontab (запускать можно хоть каждый час — письмо уйдёт
 * только в запланированный в настройках момент):
 *
 *   0 * * * * /usr/bin/php /путь/к/сайту/cms/cron/backup.php >> /tmp/cms-backup.log 2>&1
 *
 * Из браузера скрипт недоступен: каталог cron/ лежит вне index.php, а этот
 * файл отказывается работать не в CLI.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/init.php';

$res = backup_send(false);
fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . ($res['status'] ?? 'error') . ': ' . ($res['message'] ?? '') . PHP_EOL);
exit(!empty($res['ok']) ? 0 : 1);
