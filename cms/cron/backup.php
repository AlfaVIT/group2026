<?php
/**
 * Отправка бекапа базы внешним планировщиком (cron хостинга).
 *
 * Каждый запуск создаёт и отправляет резервную копию — расписания внутри
 * сайта нет, периодичность задаётся в crontab. Пример:
 *
 *   0 3 * * * /usr/bin/php /путь/к/сайту/cms/cron/backup.php >> /tmp/cms-backup.log 2>&1
 *
 * Из браузера скрипт недоступен: каталог cron/ лежит вне index.php, а этот
 * файл отказывается работать не в CLI.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/app/init.php';

$res = backup_send();
fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . ($res['status'] ?? 'error') . ': ' . ($res['message'] ?? '') . PHP_EOL);
exit(!empty($res['ok']) ? 0 : 1);
