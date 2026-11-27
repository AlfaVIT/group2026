<?php

/**
 * Тест обслуживания: бекап запускается внешним планировщиком (php tests/backup_test.php).
 *
 * Проверяет, что расписания внутри сайта больше нет: настройки периодичности
 * удаляются миграцией, статус бекапов ограничен «последней отправкой», а
 * создание снимка базы работает как раньше.
 */

$tmpDir = sys_get_temp_dir() . '/cms-backup-' . getmypid();
@mkdir($tmpDir, 0777, true);
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}

putenv('CMS_DB_PATH=' . $tmpDir . '/cms.sqlite');
putenv('CMS_CRYPTO_KEY_FILE=' . $tmpDir . '/keys/master.key');
putenv('CMS_CRYPTO=0');

require dirname(__DIR__) . '/cms/app/init.php';

$failures = 0;
$checks = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "  ok   $name\n";
        return;
    }
    $failures++;
    echo "  FAIL $name" . ($detail !== '' ? " ($detail)" : '') . "\n";
}

function eq(string $name, $expected, $actual): void
{
    check($name, $expected === $actual, 'ожидалось ' . var_export($expected, true) . ', получено ' . var_export($actual, true));
}

function backupKeys(): array
{
    return array_column(
        Db::fetchAll("SELECT key FROM settings WHERE key LIKE 'backup\\_%' ESCAPE '\\' ORDER BY key"),
        'key'
    );
}

echo "Установка\n";
install_schema();
eq('версия схемы', '33', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
check('настроек расписания нет', !array_intersect(
    ['backup_enabled', 'backup_freq', 'backup_time', 'backup_weekday', 'backup_day'],
    backupKeys()
), implode(',', backupKeys()));

echo "\nМиграция со старой версии\n";
Db::q("INSERT OR REPLACE INTO settings (key, value) VALUES
    ('backup_enabled', '1'), ('backup_freq', 'weekly'), ('backup_time', '04:30'),
    ('backup_weekday', '2'), ('backup_day', '10')");
Db::q("UPDATE settings SET value = '32' WHERE key = 'schema_version'");
run_migrations();
eq('версия схемы после миграции', '33', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
check('настройки периодичности удалены', !array_intersect(
    ['backup_enabled', 'backup_freq', 'backup_time', 'backup_weekday', 'backup_day'],
    backupKeys()
), implode(',', backupKeys()));

echo "\nСтатус и отправка\n";
$status = backup_status();
eq('поля статуса', ['last', 'last_error'], array_keys($status));
eq('последней отправки нет', '', $status['last']);

Setting::set('backup_last', '2026-11-27 03:10:00');
Setting::set('backup_last_error', 'тестовая ошибка');
$status = backup_status();
eq('последняя отправка читается', '2026-11-27 03:10:00', $status['last']);
eq('последняя ошибка читается', 'тестовая ошибка', $status['last_error']);

Setting::set('backup_last_error', '');
$res = backup_send();
eq('без почты администратора — ошибка', false, $res['ok']);
eq('статус ошибки', 'error', $res['status']);
check('сообщение указывает на почту администратора', strpos($res['message'], 'почту администратора') !== false, $res['message']);

echo "\nСнимок базы\n";
$snap = backup_snapshot();
check('снимок создан', isset($snap['path']) && is_file($snap['path']), var_export($snap, true));
if (isset($snap['path'])) {
    check('снимок не пустой', (int)filesize($snap['path']) > 0);
    @unlink($snap['path']);
}

foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir . '/keys');
@rmdir($tmpDir);

echo "\n" . ($failures === 0 ? "Все проверки пройдены ($checks)" : "Провалено $failures из $checks") . "\n";
exit($failures === 0 ? 0 : 1);
