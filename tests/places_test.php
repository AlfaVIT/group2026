<?php

/**
 * Тест: активность мест встреч (php tests/places_test.php).
 *
 * Проверяет миграцию v31 (колонка places.is_active), значение по умолчанию
 * и выборку мест для формы встречи: в списке только активные места, а уже
 * выбранное место показывается всегда — даже если его деактивировали.
 * Работает на временной базе, прод не трогает.
 */

$tmpDir = sys_get_temp_dir() . '/cms-places-' . getmypid();
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

function placeColumns(): array
{
    return array_column(Db::fetchAll('PRAGMA table_info(places)'), 'name');
}

/** Выборка из MeetingController::edit для формы встречи. */
function placesForForm(int $currentPlaceId): array
{
    return Db::fetchAll(
        'SELECT id, title FROM places WHERE is_active = 1 OR id = ? ORDER BY title',
        [$currentPlaceId]
    );
}

echo "Установка схемы\n";
install_schema();
eq('версия схемы', '32', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
check('колонка is_active есть', in_array('is_active', placeColumns(), true));

$homeId = Db::insert('places', ['title' => 'Дом Ивана', 'street' => 'ул. Ленина']);
$dachaId = Db::insert('places', ['title' => 'Дача', 'street' => 'ул. Мира']);
eq('новое место активно', 1, (int)Db::value('SELECT is_active FROM places WHERE id = ?', [$homeId]));

Db::update('places', ['is_active' => 0], ['id' => $dachaId]);
eq('флаг активности сохраняется', 0, (int)Db::value('SELECT is_active FROM places WHERE id = ?', [$dachaId]));

echo "\nВыбор мест в форме встречи\n";
eq('новая встреча: только активные', ['Дом Ивана'], array_column(placesForForm(0), 'title'));
eq('редактирование встречи с неактивным местом: оно видно',
    ['Дача', 'Дом Ивана'], array_column(placesForForm($dachaId), 'title'));
eq('редактирование встречи с активным местом: без лишних',
    ['Дом Ивана'], array_column(placesForForm($homeId), 'title'));

echo "\nМиграция старой базы (v30 → v31)\n";
// имитируем базу до v31: колонки нет, в places уже есть строки
Db::q('ALTER TABLE places DROP COLUMN is_active');
check('колонка удалена (имитация старой схемы)', !in_array('is_active', placeColumns(), true));
Db::q("UPDATE settings SET value = '30' WHERE key = 'schema_version'");
run_migrations();
check('миграция вернула колонку', in_array('is_active', placeColumns(), true));
eq('версия схемы после миграции', '32', (string)Db::value("SELECT value FROM settings WHERE key = 'schema_version'"));
eq('старое место осталось активным', 1, (int)Db::value('SELECT is_active FROM places WHERE id = ?', [$homeId]));

echo "\n" . ($failures === 0 ? "Все проверки пройдены ($checks)" : "Провалено $failures из $checks") . "\n";
exit($failures === 0 ? 0 : 1);
