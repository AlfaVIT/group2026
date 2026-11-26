<?php

/**
 * Тест слежения за старшим (php tests/follow_test.php).
 *
 * Проверяет состояние meeting_follow: ведущим может быть только один старший,
 * подписка участников, передачу позиции, «протухание» ведущего и уборку
 * подписок, когда вести больше некому. HTTP не используется.
 */

$tmpDir = sys_get_temp_dir() . '/cms-follow-' . getmypid();
@mkdir($tmpDir . '/cache', 0777, true);
foreach (glob($tmpDir . '/cache/*') ?: [] as $f) {
    @unlink($f);
}

putenv('CMS_DB_PATH=' . $tmpDir . '/cms.sqlite');
putenv('CMS_CRYPTO_KEY_FILE=' . $tmpDir . '/keys/master.key');
putenv('CMS_VESPERS_CACHE=' . $tmpDir . '/cache');
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

function add_user(string $name, string $role, string $term = 'брат'): int
{
    return Db::insert('users', [
        'address_term' => $term,
        'name' => $name,
        'role' => $role,
        'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
    ]);
}

echo "Установка\n";
install_schema();
eq('версия схемы', '32', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
$cols = array_column(Db::fetchAll('PRAGMA table_info(meeting_follow)'), 'name');
check('таблица meeting_follow есть', $cols !== [], implode(',', $cols));
check('колонки на месте', !array_diff(['meeting_id', 'user_id', 'is_leader', 'position', 'updated_at'], $cols), implode(',', $cols));

$senior = add_user('Иван', 'Старший');
$senior2 = add_user('Пётр', 'Старший');
$guest = add_user('Гость', 'Гость');
$member = add_user('Мария', 'СестраБрат', 'сестра');
$otherMeeting = Db::insert('meetings', ['date' => '2026-12-01T18:00']);
$meeting = Db::insert('meetings', ['date' => '2026-12-02T18:00']);

echo "\nВедущий\n";
$state = follow_state($meeting, $senior, true);
check('сначала ведущего нет', $state['leader'] === null);
check('роль пуста', $state['role'] === null);

follow_set_leader($meeting, $senior, true, 0.25);
$state = follow_state($meeting, $senior, true);
check('ведущий появился', $state['leader'] !== null);
eq('имя ведущего', 'брат Иван', $state['leader']['name'] ?? '');
eq('позиция ведущего', 0.25, $state['leader']['position'] ?? null);
eq('своя роль — ведущий', 'leader', $state['role']);

$state = follow_state($meeting, $guest);
eq('гость видит ведущего', 'брат Иван', $state['leader']['name'] ?? '');
eq('гость не может вести', false, $state['can_lead']);

echo "\nПодписка\n";
follow_set_follower($meeting, $guest, true);
eq('гость подписался', 'follower', follow_state($meeting, $guest)['role']);
eq('счётчик следящих', 1, follow_state($meeting, $senior, true)['followers']);
follow_set_follower($meeting, $member, true);
eq('счётчик следящих 2', 2, follow_state($meeting, $senior, true)['followers']);
follow_set_follower($meeting, $member, false);
eq('участник отписался', null, follow_state($meeting, $member)['role']);
eq('счётчик следящих 1', 1, follow_state($meeting, $senior, true)['followers']);
eq('другая встреча не затронута', null, follow_state($otherMeeting, $guest)['leader']);

echo "\nПозиция\n";
follow_update_position($meeting, $senior, 0.7);
eq('позиция обновилась', 0.7, follow_state($meeting, $guest)['leader']['position']);
follow_update_position($meeting, $senior, 5.0);
eq('позиция зажата в 1', 1.0, follow_state($meeting, $guest)['leader']['position']);
follow_update_position($meeting, $guest, 0.1);
eq('не ведущий не меняет позицию', 1.0, follow_state($meeting, $senior, true)['leader']['position']);

echo "\nСмена ведущего\n";
follow_set_leader($meeting, $senior2, true, 0.1);
$state = follow_state($meeting, $senior, true);
eq('прежний ведущий больше не ведёт', null, $state['role']);
eq('новый ведущий', 'брат Пётр', $state['leader']['name'] ?? '');
eq('подписка сохранилась при смене ведущего', 'follower', follow_state($meeting, $guest)['role']);

follow_set_leader($meeting, $senior2, false);
$state = follow_state($meeting, $guest);
eq('ведущего нет', null, $state['leader']);
eq('подписки сброшены', null, $state['role']);
eq('следящих нет', 0, $state['followers']);

echo "\nПротухание\n";
follow_set_leader($meeting, $senior, true, 0.3);
follow_set_follower($meeting, $guest, true);
Db::q('UPDATE meeting_follow SET updated_at = ? WHERE meeting_id = ?', [time() - FOLLOW_LEADER_TTL - 5, $meeting]);
$state = follow_state($meeting, $guest);
check('устаревший ведущий не показывается', $state['leader'] === null);
eq('подписка устаревшего состояния не действует', null, $state['role']);
follow_clear_stale($meeting);
eq('устаревшие строки удалены', 0, (int)(Db::value('SELECT COUNT(*) FROM meeting_follow WHERE meeting_id = ?', [$meeting]) ?? 0));

echo "\n";
echo $failures === 0 ? "Все проверки пройдены ($checks)\n" : "Провалено $failures из $checks\n";
foreach (glob($tmpDir . '/cache/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir . '/cache');
@rmdir($tmpDir);
exit($failures === 0 ? 0 : 1);
