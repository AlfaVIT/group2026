<?php

/**
 * Интеграционный тест: шифрование на уровне Db (php tests/db_crypto_test.php [1]).
 *
 * Аргумент 1 включает шифрование (CMS_CRYPTO=1). Тест гоняется в двух режимах:
 * с включённым и выключенным шифрованием — оба должны работать.
 * Работает на временной базе, прод не трогает.
 */

$tmpDir = sys_get_temp_dir() . '/cms-db-crypto-' . getmypid();
@mkdir($tmpDir, 0777, true);
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    @unlink($f);
}

putenv('CMS_DB_PATH=' . $tmpDir . '/cms.sqlite');
putenv('CMS_CRYPTO_KEY_FILE=' . $tmpDir . '/keys/master.key');
putenv('CMS_CRYPTO=' . (($argv[1] ?? '0') === '1' ? '1' : '0'));

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

function rawUser(int $id): array
{
    return Db::fetchAllRaw('SELECT * FROM users WHERE id = ?', [$id])[0] ?? [];
}

$on = false;
echo "Режим: CMS_CRYPTO=" . (getenv('CMS_CRYPTO') ?: '0') . "\n";
check('openssl доступен', Crypto::available());

echo "\nУстановка\n";
install_schema();
$on = Crypto::enabled();
echo $on ? "  → шифрование ВКЛЮЧЕНО\n" : "  → шифрование выключено\n";
eq('версия схемы', '30', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
check('ключ создан', is_file(Crypto::keyPath()));
check('ключ валиден', Crypto::keyIsValid());
check('crypto_ready', crypto_ready());
$cols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
check('колонка email_idx есть', in_array('email_idx', $cols, true));

echo "\nПользователи\n";
$errors = install_create_admin([
    'address_term' => 'сестра',
    'name' => 'Администратор Установки',
    'login_or_email' => 'root@example.com',
    'password' => 'secret123',
    'password_confirm' => 'secret123',
]);
eq('установщик создаёт администратора без ошибок', [], $errors);
$root = user_find_by_login('root@example.com');
check('администратор находится по email', (bool)$root, var_export($errors, true));
$rootRaw = $root ? rawUser((int)$root['id']) : [];
if ($on && $root) {
    check('имя администратора зашифровано', Crypto::isEncrypted($rootRaw['name'] ?? ''));
}
$errors = install_create_admin([
    'address_term' => 'сестра', 'name' => 'Дубль', 'login_or_email' => 'ROOT@example.com',
    'password' => 'secret123', 'password_confirm' => 'secret123',
]);
check('повторный администратор с тем же email отклоняется', $errors !== []);
$id = Db::insert('users', [
    'name' => 'Иван Петров',
    'address_term' => 'брат',
    'phone' => '+7 916 000-00-01',
    'email' => 'Ivan@Example.COM',
    'birthday' => '1990-05-17',
    'login' => 'ivan',
    'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
    'role' => 'СестраБрат',
    'is_active' => 1,
]);
$raw = rawUser($id);
if ($on) {
    check('имя зашифровано', Crypto::isEncrypted($raw['name']), $raw['name']);
    check('телефон зашифрован', Crypto::isEncrypted($raw['phone']));
    check('email зашифрован', Crypto::isEncrypted($raw['email']));
    check('дата рождения зашифрована', Crypto::isEncrypted($raw['birthday']));
} else {
    eq('имя открыто', 'Иван Петров', $raw['name']);
}
check('слепой индекс email заполнен', strlen((string)$raw['email_idx']) === 32, (string)$raw['email_idx']);
if ($on) {
    check('открытый email в базе не встречается', strpos((string)$raw['email'], 'Example') === false);
}

$u = Db::fetch('SELECT * FROM users WHERE id = ?', [$id]);
eq('чтение имени', 'Иван Петров', $u['name']);
eq('чтение телефона', '+7 916 000-00-01', $u['phone']);
eq('чтение email (регистр сохранён)', 'Ivan@Example.COM', $u['email']);
eq('чтение даты рождения', '1990-05-17', $u['birthday']);

echo "\nПоиск для входа\n";
eq('вход по email в другом регистре', $id, (int)(user_find_by_login('ivan@example.com')['id'] ?? 0));
eq('вход по логину', $id, (int)(user_find_by_login('IVAN')['id'] ?? 0));
eq('вход с пробелами по краям', $id, (int)(user_find_by_login(' ivan@example.com ')['id'] ?? 0));
check('чужой не находится', user_find_by_login('petr@example.com') === null);
eq('поиск для восстановления пароля', $id, (int)(user_find_by_email('IVAN@example.com')['id'] ?? 0));
check('email занят', user_email_taken('ivan@EXAMPLE.com', 0));
check('свой email не занят', !user_email_taken('ivan@example.com', $id));

echo "\nОбновление\n";
Db::update('users', ['phone' => '+7 916 000-00-02', 'email' => 'ivan2@example.com'], ['id' => $id]);
$raw = rawUser($id);
eq('телефон обновлён и читается', '+7 916 000-00-02', Db::value('SELECT phone FROM users WHERE id = ?', [$id]));
check('старый email больше не находится', user_find_by_login('ivan@example.com') === null);
eq('новый email находится', $id, (int)(user_find_by_login('ivan2@example.com')['id'] ?? 0));

echo "\nПустые значения\n";
$id2 = Db::insert('users', [
    'name' => 'Пётр Сидоров', 'address_term' => 'брат', 'phone' => '', 'email' => '',
    'login' => 'petr', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT), 'is_active' => 1,
]);
$raw2 = rawUser($id2);
eq('пустой телефон остался пустым', '', $raw2['phone']);
eq('пустой email остался пустым', '', $raw2['email']);
eq('пустой индекс email', '', $raw2['email_idx']);

echo "\nАдреса, заметки, касса\n";
$pid = Db::insert('places', [
    'title' => 'Дом Ивана', 'owner_id' => $id, 'street' => 'ул. Ленина', 'house' => '5', 'apartment' => '12',
]);
$placeRaw = Db::fetchAllRaw('SELECT * FROM places WHERE id = ?', [$pid])[0];
if ($on) {
    check('улица зашифрована', Crypto::isEncrypted($placeRaw['street']));
    check('квартира зашифрована', Crypto::isEncrypted($placeRaw['apartment']));
}
eq('адрес читается', 'ул. Ленина', Db::value('SELECT street FROM places WHERE id = ?', [$pid]));
check('название места открыто (по нему сортировка)', $placeRaw['title'] === 'Дом Ивана');

$mid = Db::insert('meetings', ['date' => '2026-01-10']);
$nid = Db::insert('meeting_notes', [
    'meeting_id' => $mid, 'user_id' => $id, 'body' => 'Личная заметка', 'format' => 'text', 'visibility' => 'private',
]);
$noteRaw = Db::fetchAllRaw('SELECT * FROM meeting_notes WHERE id = ?', [$nid])[0];
if ($on) {
    check('текст заметки зашифрован', Crypto::isEncrypted($noteRaw['body']));
}
eq('заметка читается', 'Личная заметка', Db::value('SELECT body FROM meeting_notes WHERE id = ?', [$nid]));

$eid = Db::insert('treasury_entries', [
    'date' => '2026-01-10', 'purpose' => 'Регулярный взнос', 'amount' => 1500.5,
    'note' => 'за январь', 'from_user_id' => $id, 'from_text' => 'Иван',
]);
$entryRaw = Db::fetchAllRaw('SELECT * FROM treasury_entries WHERE id = ?', [$eid])[0];
if ($on) {
    check('примечание казны зашифровано', Crypto::isEncrypted($entryRaw['note']));
    check('подпись плательщика зашифрована', Crypto::isEncrypted($entryRaw['from_text']));
}
check('сумма осталась числом', is_numeric($entryRaw['amount']), var_export($entryRaw['amount'], true));
eq('сумма не изменилась', 1500.5, (float)Db::value('SELECT SUM(amount) FROM treasury_entries'));
eq('статья не зашифрована (нужна для группировки)', 'Регулярный взнос', $entryRaw['purpose']);

$vid = Db::insert('visits', ['user_id' => $id, 'ip' => '10.1.2.3', 'user_agent' => 'Test UA']);
$visitRaw = Db::fetchAllRaw('SELECT * FROM visits WHERE id = ?', [$vid])[0];
if ($on) {
    check('IP зашифрован', Crypto::isEncrypted($visitRaw['ip']));
}
eq('IP читается', '10.1.2.3', Db::value('SELECT ip FROM visits WHERE id = ?', [$vid]));

echo "\nСортировка и годы рождения\n";
$sorted = sort_by_name([['name' => 'Яков'], ['name' => 'Анна'], ['name' => 'Борис']]);
eq('сортировка по имени', 'Анна,Борис,Яков', implode(',', array_column($sorted, 'name')));
Db::insert('users', [
    'name' => 'Старый Участник', 'address_term' => 'брат', 'birthday' => '1985-11-01',
    'login' => 'old', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT), 'is_active' => 1,
]);
$years = birthday_year_options();
check('годы рождения из зашифрованного поля', in_array('1990', $years, true) && in_array('1985', $years, true), implode(',', $years));
eq('годы по убыванию', ['1990', '1985'], $years);

echo "\nПерешифровка старых данных\n";
Db::q("INSERT INTO places (title, street, note) VALUES ('Старое место', 'ул. Мира', 'старая заметка')");
$openBefore = crypto_count_open();
if ($on) {
    check('есть открытые значения', $openBefore > 0, (string)$openBefore);
    $res = crypto_sweep();
    check('перешифровка выполнена', $res['ok'] && $res['done'], var_export($res, true));
    eq('открытых значений не осталось', 0, crypto_count_open());
    $legacyRaw = Db::fetchAllRaw("SELECT * FROM places WHERE title = 'Старое место'")[0];
    check('старая строка зашифрована', Crypto::isEncrypted($legacyRaw['street']));
    eq('старая строка читается', 'ул. Мира', Db::value("SELECT street FROM places WHERE title = 'Старое место'"));
    $again = crypto_sweep();
    eq('повторная перешифровка ничего не портит', 0, $again['encrypted']);
} else {
    $res = crypto_sweep();
    check('при выключенном шифровании перешифровка отказывает', !$res['ok']);
    check('открытые значения остались', crypto_count_open() > 0);
}

echo "\nПовторная миграция\n";
Db::q("UPDATE settings SET value = '29' WHERE key = 'schema_version'");
run_migrations();
eq('версия схемы снова 30', '30', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
eq('вход после повторной миграции', $id, (int)(user_find_by_login('ivan2@example.com')['id'] ?? 0));

echo "\nПроизводительность на реальных выборках\n";
$t0 = hrtime(true);
$rows = Db::fetchAll('SELECT * FROM users');
$us = (hrtime(true) - $t0) / 1000;
echo sprintf("  чтение %d пользователей: %.0f мкс (%.1f мкс на строку)\n", count($rows), $us, $us / max(1, count($rows)));

echo "\n" . ($failures === 0 ? "ВСЁ ХОРОШО: $checks проверок" : "ПРОВАЛОВ: $failures из $checks") . "\n";
@unlink(DB_PATH);
exit($failures === 0 ? 0 : 1);
