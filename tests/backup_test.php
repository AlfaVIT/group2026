<?php

/**
 * Тест обслуживания: бекап запускается внешним планировщиком и управляется
 * переключателем отправки (php tests/backup_test.php).
 *
 * Проверяет, что расписания внутри сайта нет (только «отправлять / не
 * отправлять»), миграция не теряет выключатель, а при выключенной отправке
 * бекап не создаётся и не уходит.
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

function settingsValue(string $key): ?string
{
    $value = Db::value('SELECT value FROM settings WHERE key = ?', [$key]);
    return $value === null ? null : (string)$value;
}

echo "Установка\n";
install_schema();
eq('версия схемы', '34', settingsValue('schema_version'));
eq('отправка включена по умолчанию', '1', settingsValue('backup_enabled'));
check('расписания внутри сайта нет', settingsValue('backup_freq') === null
    && settingsValue('backup_time') === null
    && settingsValue('backup_weekday') === null
    && settingsValue('backup_day') === null);

$status = backup_status();
eq('поля статуса', ['enabled', 'last', 'last_error'], array_keys($status));
eq('включено', true, $status['enabled']);
eq('последней отправки нет', '', $status['last']);

echo "\nМиграция со старой версии\n";
// имитируем базу v33, где выключателя не было
Db::q("DELETE FROM settings WHERE key = 'backup_enabled'");
Db::q("UPDATE settings SET value = '33' WHERE key = 'schema_version'");
run_migrations();
eq('версия схемы после миграции', '34', settingsValue('schema_version'));
eq('выключатель появился включённым', '1', settingsValue('backup_enabled'));

echo "\nВыключенная отправка\n";
Setting::set('backup_enabled', '0');
$status = backup_status();
eq('выключатель читается', false, $status['enabled']);

Setting::set('backup_last', '2026-11-27 03:10:00');
$res = backup_send();
eq('статус — выключено', 'disabled', $res['status']);
eq('без ошибки', true, $res['ok']);
eq('последняя отправка не изменилась', '2026-11-27 03:10:00', settingsValue('backup_last'));
check('сообщение про выключенную отправку', strpos($res['message'], 'выключена') !== false, $res['message']);

echo "\nВключённая отправка\n";
Setting::set('backup_enabled', '1');
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
