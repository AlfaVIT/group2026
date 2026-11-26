<?php

/**
 * Тест постной вечерни (php tests/vespers_lent_test.php).
 *
 * Проверяет выбор постного чина, сборку текста для Рождественского поста и
 * Великого поста и список дней, которые по-прежнему показываются из molitva.app
 * (Страстная, Пасха, Вербное, Прощёное воскресенье, Введение).
 *
 * Сеть не используется: ответы API кладутся в файловый кэш (CMS_VESPERS_CACHE).
 */

$tmpDir = sys_get_temp_dir() . '/cms-vespers-' . getmypid();
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

function seed_day(string $date, array $data): void
{
    $day = array_merge([
        'title' => 'Седмица', 'glas' => 1, 'lent' => '', 'readings' => [], 'bReadings' => [], 'saints' => '',
    ], $data);
    file_put_contents(vespers_cache_dir() . '/day_' . $date . '.json', json_encode($day, JSON_UNESCAPED_UNICODE));
}

function seed_parts(string $date, array $parts): void
{
    file_put_contents(vespers_cache_dir() . '/parts_' . $date . '.json', json_encode($parts, JSON_UNESCAPED_UNICODE));
}

function seed_readings(string $date): void
{
    file_put_contents(vespers_cache_dir() . '/readings_' . $date . '.json', json_encode([], JSON_UNESCAPED_UNICODE));
}

$NATIVITY = 'Рождественский (Филиппов) пост';
$GREAT_FAST_FEAST_MARK = '<span class="normaltext"><img src="/assets/icons/6.svg" alt="Совершается служба великому празднику"/>Введение</span>';

// Рождественский пост: понедельник 30.11.2026 (чин 01.12)
seed_day('2026-11-30', ['lent' => $NATIVITY]);
seed_day('2026-12-01', ['lent' => $NATIVITY]);
seed_parts('2026-12-01', []);
seed_readings('2026-11-30');

// Суббота поста 28.11.2026 (чин 29.11)
seed_day('2026-11-28', ['lent' => $NATIVITY]);
seed_day('2026-11-29', ['lent' => $NATIVITY]);
seed_parts('2026-11-29', []);
seed_readings('2026-11-28');

// Введение 04.12.2026 — канун 03.12 (великий праздник, обычный чин)
seed_day('2026-12-03', ['lent' => $NATIVITY]);
seed_day('2026-12-04', ['lent' => $NATIVITY, 'saints' => $GREAT_FAST_FEAST_MARK]);
seed_parts('2026-12-04', [
    'vespers' => [
        'Стихиры на Господи взываю' => [['value' => '<p>Стихира праздника</p>']],
    ],
]);
seed_readings('2026-12-03');

// Великий пост: вторник 16.03.2027 (чин 17.03) и воскресенье 21.03.2027
seed_day('2027-03-16', []);
seed_day('2027-03-17', []);
seed_parts('2027-03-17', []);
seed_readings('2027-03-16');
seed_day('2027-03-21', []);
seed_day('2027-03-22', []);
seed_parts('2027-03-22', []);
seed_readings('2027-03-21');

// Дни, которые остаются из приложения
seed_day('2027-03-15', []);  // Прощёное воскресенье (чин 15.03)
seed_day('2027-04-24', []);  // канун Вербного воскресенья
seed_day('2027-04-25', []);
seed_day('2027-04-30', []);  // Страстная пятница
seed_day('2027-05-01', []);
seed_day('2027-05-02', []);  // Пасха
seed_day('2027-05-03', []);

// Обычный день вне поста
seed_day('2026-11-24', []);
seed_day('2026-11-25', []);
seed_parts('2026-11-25', []);
seed_readings('2026-11-24');

echo "Выбор чина\n";
check('Рождественский пост, будний день — локально', !vespers_needs_iframe('2026-11-30'));
check('Рождественский пост, будний день — постный чин', vespers_lent_script('2026-11-30'));
check('суббота поста — обычный чин', !vespers_lent_script('2026-11-28'));
check('Введение — обычный чин', !vespers_lent_script('2026-12-03'));
check('Введение — локально', !vespers_needs_iframe('2026-12-03'));
check('Великий пост, будний день — локально', !vespers_needs_iframe('2027-03-16'));
check('Великий пост — постный чин Великого поста', vespers_lent_script('2027-03-16') && vespers_is_great_lent('2027-03-16'));
check('Прощёное воскресенье — из приложения', vespers_needs_iframe('2027-03-14'));
check('Вербное воскресенье — из приложения', vespers_needs_iframe('2027-04-24'));
check('Страстная пятница — из приложения', vespers_needs_iframe('2027-04-30'));
check('Пасха — из приложения', vespers_needs_iframe('2027-05-02'));

echo "\nРождественский пост (30.11.2026)\n";
$html = vespers_text_html('2026-11-30');
check('текст собран', $html !== '');
check('постный чин без заголовка «Вход»', strpos($html, '>Вход<') === false);
check('нет псалма 103', strpos($html, 'Псалом 103') === false);
check('нет кафизмы', strpos($html, 'Кафизма') === false);
check('«Ныне отпускаешь» в постном варианте', strpos($html, '<span class="Red">Ч</span> Ныне отпускаешь') !== false);
check('краткая сугубая ектенья вместо полной',
    strpos($html, 'Помилуй нас, Боже, по великой милости Твоей') !== false
    && strpos($html, 'Возгласим все, от всей души') === false);
check('без молитвы Ефрема Сирина', strpos($html, 'Ефрема Сирина') === false);
check('тропари на месте', strpos($html, 'Тропари') !== false);
$slotKeys = array_column(vespers_reader_slots('2026-11-30'), 'key');
check('у чтецов нет кафизмы и псалма 103',
    !in_array('kafisma', $slotKeys, true) && !in_array('psalm103', $slotKeys, true));

echo "\nВеликий пост, будний день (16.03.2027)\n";
$html = vespers_text_html('2027-03-16');
check('постное окончание с молитвой Ефрема Сирина', strpos($html, 'Ефрема Сирина') !== false);
check('тропари, глас 4', strpos($html, 'Тропари, глас 4') !== false);
check('будничное окончание (12 поклонов)', strpos($html, 'Боже, будь милостив ко мне, грешному') !== false);
check('нет полной сугубой ектеньи', strpos($html, 'Возгласим все, от всей души') === false);
check('«Ныне отпускаешь» в постном варианте', strpos($html, '<span class="Red">Ч</span> Ныне отпускаешь') !== false);

echo "\nВеликий пост, воскресенье (21.03.2027)\n";
$html = vespers_text_html('2027-03-21');
check('заголовок «Вход» остаётся в воскресенье', strpos($html, '>Вход<') !== false);
check('воскресное постное окончание', strpos($html, 'Ефрема Сирина') !== false);
check('без будничных 12 поклонов', strpos($html, 'Боже, будь милостив ко мне, грешному') === false);

echo "\nОбычный день (24.11.2026)\n";
$html = vespers_text_html('2026-11-24');
check('полная сугубая ектенья', strpos($html, 'Возгласим все, от всей души') !== false);
check('«Ныне отпускаешь» обычного чина', strpos($html, '<span class="Red">Н</span> Ныне отпускаешь') !== false);
check('молитвы Ефрема Сирина нет', strpos($html, 'Ефрема Сирина') === false);

foreach (glob($tmpDir . '/cache/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmpDir . '/cache');
@rmdir($tmpDir);

echo "\n" . ($failures === 0 ? "Все проверки пройдены ($checks)" : "Провалено $failures из $checks") . "\n";
exit($failures === 0 ? 0 : 1);
