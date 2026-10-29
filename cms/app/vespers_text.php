<?php

/**
 * Парсер текста вечерни molitva.app («Вечерня братским чином»).
 *
 * По дате встречи собирает HTML стандартного чина вечерни (модуль 1127
 * приложения) из API api.c.psmb.ru и локальных фрагментов. Для особых
 * периодов (посты, Пасха, Страстная, Рождество, Крещение и т.п.) возвращает
 * признак необходимости iframe-фолбэка на molitva.app.
 *
 * Использует кэш файлового типа в data/cache/vespers/.
 */

/* ------------------------------------------------------------------ */
/* HTTP и кэш                                                         */
/* ------------------------------------------------------------------ */

function vespers_cache_dir(): string
{
    $dir = BASE_PATH . '/data/cache/vespers';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function vespers_cache_get(string $key, int $ttl = 21600)
{
    $file = vespers_cache_dir() . '/' . $key . '.json';
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            return json_decode($raw, true);
        }
    }
    return null;
}

function vespers_cache_set(string $key, $value): void
{
    $file = vespers_cache_dir() . '/' . $key . '.json';
    @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function vespers_fetch_json(string $url, string $cacheKey, int $ttl = 21600)
{
    $cached = vespers_cache_get($cacheKey, $ttl);
    if ($cached !== null) {
        return $cached;
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 20,
            'header' => "User-Agent: Mozilla/5.0 (compatible; cms-vespers)\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    vespers_cache_set($cacheKey, $data);
    return $data;
}

function vespers_day(string $date): array
{
    $d = vespers_fetch_json('https://api.c.psmb.ru/day/' . $date, 'day_' . $date);
    return is_array($d) ? $d : [];
}

function vespers_parts(string $date): array
{
    $p = vespers_fetch_json('https://api.c.psmb.ru/parts/' . $date . '/ru', 'parts_' . $date);
    return is_array($p) ? $p : [];
}

function vespers_readings_data(string $date): array
{
    $r = vespers_fetch_json('https://api.c.psmb.ru/readings/' . $date, 'readings_' . $date, 86400);
    return is_array($r) ? $r : [];
}

/* ------------------------------------------------------------------ */
/* Церковный календарь                                                 */
/* ------------------------------------------------------------------ */

/** Православная Пасха (Гаусс) — возвращает 'Y-m-d'. */
function vespers_orthodox_easter(int $year): string
{
    $a = $year % 4;
    $b = $year % 7;
    $c = $year % 19;
    $d = (19 * $c + 15) % 30;
    $e = (2 * $a + 4 * $b - $d + 34) % 7;
    $month = (int)floor(($d + $e + 114) / 31);
    $day = (($d + $e + 114) % 31) + 1;
    // формула даёт дату по юлианскому календарю; переводим в григорианский (+13 дней в 1900–2099)
    return date('Y-m-d', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day) . ' +13 days'));
}

/** Сдвиг даты от Пасхи её года (0 — сама Пасха, минус — до, плюс — после). */
function vespers_offset_days(string $date): int
{
    $year = (int)substr($date, 0, 4);
    $easter = vespers_orthodox_easter($year);
    $diff = (int)((strtotime($date) - strtotime($easter)) / 86400);
    // смещение может пересекать границу года (конец декабря — январь следующего года)
    $e2 = vespers_orthodox_easter($year + 1);
    $diff2 = (int)((strtotime($date) - strtotime($e2)) / 86400);
    return abs($diff) <= abs($diff2) ? $diff : $diff2;
}

/**
 * Пост, действующий на литургический день W (Y-m-d): '', 'velikiy', 'petrov',
 * 'uspensky' или 'rozhdestvensky'. Считается от Пасхи и фиксированных дат.
 */
function vespers_fast_period(string $W): string
{
    $year = (int)substr($W, 0, 4);
    $easter = vespers_orthodox_easter($year);
    $offset = (int)((strtotime($W) - strtotime($easter)) / 86400);
    // Великий пост + Страстная седмица: [Пасха-48, Пасха-1]
    if ($offset >= -48 && $offset <= -1) {
        return 'velikiy';
    }
    // Петров пост: понедельник после Недели Всех Святых (Пасха+57) до кануна Петрова дня (11 июля)
    $petrovEnd = strtotime($year . '-07-11');
    $tsW = strtotime($W);
    if ($offset >= 57 && $tsW <= $petrovEnd) {
        return 'petrov';
    }
    // Успенский пост: 14–27 августа (новый стиль)
    $md = substr($W, 5);
    if ($md >= '08-14' && $md <= '08-27') {
        return 'uspensky';
    }
    // Рождественский (Филиппов) пост: 28 ноября – 6 января (может пересекать год)
    $easterPrev = vespers_orthodox_easter($year - 1);
    $offsetPrev = (int)((strtotime($W) - strtotime($easterPrev)) / 86400);
    if ($offsetPrev >= 57 && ($md >= '11-28' || $md <= '01-06')) {
        return 'rozhdestvensky';
    }
    return '';
}

/** Нужен ли iframe-фолбэк для даты вечерни (URL-дата a). */
function vespers_needs_iframe(string $dateA): bool
{
    $W = date('Y-m-d', strtotime($dateA . ' +1 day'));
    $dayW = vespers_day($W);
    $title = (string)($dayW['title'] ?? '');
    $lent = $dayW['lent'] ?? null;

    if ($title === 'Рождество Христово' || $title === 'Рождество Господа нашего Иисуса Христа'
        || $title === 'Крещение Господне' || $title === 'Святое Богоявление') {
        return true;
    }
    if ($lent !== null && $lent !== '') {
        return true;
    }
    if (vespers_fast_period($W) !== '') {
        return true;
    }

    $offset = vespers_offset_days($dateA);
    if ($offset >= -57 && $offset <= 50) {
        return true;
    }

    // Димитриевская родительская суббота (пятница 1–8 ноября)
    $ts = strtotime($dateA);
    if ((int)date('N', $ts) === 5) {
        $d = (int)date('j', $ts);
        $m = (int)date('n', $ts);
        if ($m === 11 && $d >= 1 && $d <= 8) {
            return true;
        }
    }

    return false;
}

/* ------------------------------------------------------------------ */
/* Фильтрация parts и очистка HTML                                     */
/* ------------------------------------------------------------------ */

/**
 * Достаёт значения parts по пути вида "vespers.Стихиры на Господи взываю"
 * или "shared.Тропари", фильтруя записи по службе.
 * Включаются записи с services === null либо содержащие serviceType.
 */
function vespers_parts_filtered(array $parts, string $path, ?string $serviceType = 'Вечерня'): array
{
    $node = $parts;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($node) || !array_key_exists($seg, $node)) {
            return [];
        }
        $node = $node[$seg];
    }
    if (!is_array($node)) {
        return [];
    }
    $result = [];
    foreach ($node as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $value = isset($entry['value']) ? (string)$entry['value'] : '';
        if ($value === '') {
            continue;
        }
        $services = $entry['services'] ?? null;
        if (is_array($services)) {
            if (!in_array($serviceType, $services, true)) {
                continue;
            }
        }
        $result[] = $value;
    }
    return $result;
}

/** Собирает значение из parts по одному пути с проверкой «ЗАМЕНА». */
function vespers_parts_block(array $parts, string $path, ?string $serviceType = 'Вечерня'): array
{
    $values = vespers_parts_filtered($parts, $path, $serviceType);
    if (!$values) {
        return ['values' => [], 'replace' => false];
    }
    $replace = false;
    foreach ($values as $v) {
        if (strpos($v, 'ЗАМЕНА') !== false) {
            $replace = true;
            break;
        }
    }
    return ['values' => $values, 'replace' => $replace];
}

/** Убирает из parts-HTML аудио, тултипы, кнопки, svg и css-обвязку приложения. */
function vespers_clean(string $html): string
{
    // служебные маркеры Parts-компонента
    $html = str_replace(['ЗАМЕНА', 'НЕТЗАМЕНЫ', 'УБРАТЬИНЫНЕ', 'УБРАТЬСЛАВА'], '', $html);

    $html = preg_replace('~<audio\b.*?</audio>~is', '', $html);
    // аудио-плеер приложения (кнопка + прогресс-бар + таймкод)
    $html = preg_replace('~<div\s*>\s*<div\s*>\s*<button\b[^>]*>\s*<svg\b.*?</svg>\s*</button>.*?<span>00:00</span>\s*<span> / </span>\s*<span>00:\d{2}</span>\s*</div>\s*</div>~is', '', $html);
    $html = preg_replace('~<button\b.*?</button>~is', '', $html);
    $html = preg_replace('~<svg\b.*?</svg>~is', '', $html);
    $html = preg_replace('~<span[^>]*data-tooltipped.*?</span>~is', '', $html);
    $html = preg_replace('~<div class="css-[^"]*"></div>~i', '', $html);
    $html = preg_replace('~<div class="css-[^"]*">~i', '', $html);
    $html = preg_replace('~<div class="css-[^"]*"\s*>~i', '', $html);
    $html = preg_replace('~<div class=" css-~i', '<div class="', $html);

    // атрибуты приложения
    $html = preg_replace('~\s(id|class)="r-[^"]*"~i', '', $html);
    $html = preg_replace('~\s(id|class)="css-[^"]*"~i', '', $html);
    $html = preg_replace('~\s(data-saint|aria-label|aria-describedby)="[^"]*"~i', '', $html);
    $html = preg_replace('~\s(role|tabindex|aria-expanded|aria-haspopup|aria-controls|aria-activedescendant)="[^"]*"~i', '', $html);
    $html = preg_replace('~\sstyle="[^"]*"~i', '', $html);
    $html = preg_replace('~<img\b[^>]*>~i', '', $html);

    // пустые абзацы/обёртки
    $html = preg_replace('~<p class="P">\s*</p>~i', '', $html);
    $html = preg_replace('~<div>\s*</div>~i', '', $html);

    return trim($html);
}

/** Очищает saints-блок: убирает иконки, оставляет текст ссылок. */
function vespers_clean_saints(string $html): string
{
    $html = preg_replace('~<img\b[^>]*>~i', '', $html);
    $html = preg_replace('~<a\b[^>]*>~i', '', $html);
    $html = preg_replace('~</a>~i', '', $html);
    $html = preg_replace('~<span\b[^>]*>~i', '', $html);
    $html = preg_replace('~</span>~i', '', $html);
    return trim($html);
}

/* ------------------------------------------------------------------ */
/* Фрагменты                                                           */
/* ------------------------------------------------------------------ */

function vespers_frag(string $name): string
{
    $file = BASE_PATH . '/templates/' . theme_dir() . '/partials/vespers/' . $name . '.html';
    $c = is_file($file) ? @file_get_contents($file) : false;
    return $c === false ? '' : trim(vespers_clean($c));
}

/* ------------------------------------------------------------------ */
/* Чтения                                                              */
/* ------------------------------------------------------------------ */

/** Ссылки чтения вечерни для URL-даты a. */
function vespers_reading_refs(array $dayA): array
{
    $be = $dayA['bReadings']['Вечером'] ?? null;
    if (is_array($be) && $be) {
        return $be;
    }
    $vn = $dayA['readings']['Вечерня'] ?? null;
    if (is_array($vn) && $vn) {
        return $vn;
    }
    return [];
}

/**
 * Упорядоченный список отрывков чтения вечерни для URL-даты a.
 * Ключ каждого отрывка — нормализованная ссылка (стабильна при изменении метки).
 */
function vespers_reading_passages(array $dayA): array
{
    $out = [];
    foreach (vespers_reading_refs($dayA) as $label => $refs) {
        $label = (string)$label;
        foreach ((array)$refs as $ref) {
            $ref = (string)$ref;
            if ($ref === '') {
                continue;
            }
            $out[] = ['key' => vespers_normalize_ref($ref), 'label' => $label, 'ref' => $ref];
        }
    }
    return $out;
}

/** Нормализует ссылку для сопоставления с ключами API чтений. */
function vespers_normalize_ref(string $ref): string
{
    $ref = trim($ref);
    $ref = str_replace(['–', '—', '&ndash;', '&mdash;', "\xC2\xA0"], ['-', '-', '-', '-', ' '], $ref);
    $ref = preg_replace('/\s+/u', ' ', $ref);
    return $ref;
}

/** HTML чтения по ссылке (перевод + стихи) или '' если недоступно. */
function vespers_reading_html(string $dateA, string $label, string $ref): string
{
    $data = vespers_readings_data($dateA);
    $norm = vespers_normalize_ref($ref);
    $entry = null;
    foreach ($data as $k => $v) {
        if (vespers_normalize_ref((string)$k) === $norm) {
            $entry = $v;
            break;
        }
    }
    if ($entry === null) {
        return '';
    }

    $heading = htmlspecialchars($ref);
    if ($label !== '' && $label !== 'unnamed') {
        $heading = htmlspecialchars($label) . ' ' . $heading;
    }
    $html = '<h4 class="H4">' . $heading . '</h4>';

    $tl = $entry['translationList'][0] ?? null;
    if ($tl && !empty($tl['name'])) {
        $html .= '<p class="P"><span class="PetitInline">(' . htmlspecialchars($tl['name']) . ')</span></p>';
    }

    foreach (($entry['fragments'] ?? []) as $frag) {
        $chapter = isset($frag['chapter']) && $frag['chapter'] !== '' ? trim((string)$frag['chapter']) : '';
        if ($chapter !== '' && count($entry['fragments'] ?? []) > 1) {
            $html .= '<h4 class="H4">Глава ' . htmlspecialchars($chapter) . '</h4>';
        }
        foreach (($frag['verses'] ?? []) as $v) {
            $num = (string)($v['verse'] ?? '');
            $text = (string)($v['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $text = htmlspecialchars($text);
            $text = nl2br($text);
            $html .= '<p class="P"><span class="Red">' . htmlspecialchars($num) . '</span> ' . $text . '</p>';
        }
    }
    return $html;
}

/* ------------------------------------------------------------------ */
/* Чтецы                                                               */
/* ------------------------------------------------------------------ */

/**
 * Строка «Читает: <имя>» перед назначаемым блоком чина. Для обратной
 * совместимости учитывает записи без префикса reading: (старые ключи чтений).
 * $avatars — карта «ключ => URL аватара чтеца» (необязательно).
 */
function vespers_reader_line(array $readers, string $key, array $avatars = []): string
{
    $name = $readers[$key] ?? '';
    $baseKey = $key;
    if ($name === '' && strpos($key, 'reading:') === 0) {
        $baseKey = substr($key, 8);
        $name = $readers[$baseKey] ?? '';
    }
    if ($name === '') {
        return '';
    }
    $avatar = $avatars[$key] ?? ($avatars[$baseKey] ?? '');
    $img = $avatar !== ''
        ? '<img class="cms-avatar cms-vespers-avatar" src="' . htmlspecialchars($avatar) . '" alt="" loading="lazy"> '
        : '';
    return '<p class="P cms-vespers-reader">' . $img . '<span class="Red">Читает:</span> '
        . htmlspecialchars($name) . '</p>' . "\n";
}

/**
 * Упорядоченный список назначаемых блоков чина вечерни для URL-даты a:
 * молитвы, псалмы и чтения, присутствующие в тексте этой даты.
 * Порядок и условия должны совпадать с порядком вывода в vespers_text_html.
 */
function vespers_reader_slots(string $dateA): array
{
    $W = date('Y-m-d', strtotime($dateA . ' +1 day'));
    $dayA = vespers_day($dateA);
    $partsW = vespers_parts($W);

    $dowA = (int)date('w', strtotime($dateA));
    $mdW = date('n-j', strtotime($W));
    $isCross = $mdW === '9-27';
    $isTransfig = $mdW === '8-19';
    $skipPrayers = ($isCross || $isTransfig) && $dowA !== 0 && $dowA !== 6;
    $isHoliday = $dowA === 6 || array_key_exists('vespers', $partsW);

    $slots = [];
    if ($isHoliday) {
        $slots[] = ['key' => 'priidite', 'label' => 'Придите, поклони́мся'];
        $slots[] = ['key' => 'psalm103', 'label' => 'Псалом 103'];
    }
    if (!$skipPrayers) {
        if ($isHoliday || $dowA === 1) $slots[] = ['key' => 'prayer1', 'label' => 'Молитва (1)'];
        if ($isHoliday) $slots[] = ['key' => 'kafisma', 'label' => 'Кафизма'];
        if ($isHoliday || $dowA === 2) $slots[] = ['key' => 'prayer2', 'label' => 'Молитва (2)'];
        if ($isHoliday || $dowA === 3) $slots[] = ['key' => 'prayer3', 'label' => 'Молитва (3)'];
        if ($isHoliday || $dowA === 4) $slots[] = ['key' => 'prayer4', 'label' => 'Молитва (4)'];
        if ($isHoliday || $dowA === 5) $slots[] = ['key' => 'prayer5', 'label' => 'Молитва (5)'];
        if ($isHoliday) $slots[] = ['key' => 'prayer6', 'label' => 'Молитва (6)'];
        if ($isHoliday || $dowA === 0) $slots[] = ['key' => 'prayer7', 'label' => 'Молитва (7)'];
    }
    $slots[] = ['key' => 'prayer8', 'label' => 'Молитва (8)'];
    $slots[] = ['key' => 'vechernie_psalmi', 'label' => 'Вечерние псалмы'];
    $slots[] = ['key' => 'prokimen', 'label' => 'Прокимен'];
    foreach (vespers_reading_passages($dayA) as $p) {
        $label = ($p['label'] !== '' && $p['label'] !== 'unnamed' ? $p['label'] . ' — ' : '') . $p['ref'];
        $slots[] = ['key' => 'reading:' . $p['key'], 'label' => 'Чтение: ' . $label];
    }
    $slots[] = ['key' => 'sermon', 'label' => 'Проповедь'];
    $slots[] = ['key' => 'prayer_katekhumens', 'label' => 'Молитва об оглашаемых'];
    $slots[] = ['key' => 'udostoi', 'label' => 'Удостой, Господи'];
    $slots[] = ['key' => 'prayer9', 'label' => 'Молитва (9)'];
    $slots[] = ['key' => 'prayer_glavopriklonnaja', 'label' => 'Молитва главопреклонная'];
    $slots[] = ['key' => 'prayer_simeona', 'label' => 'Ныне отпускаеши'];
    $slots[] = ['key' => 'strisvatogo', 'label' => 'Трисвятое'];

    return $slots;
}

/* ------------------------------------------------------------------ */
/* Сборка чина                                                         */
/* ------------------------------------------------------------------ */

/**
 * Собирает HTML стандартного чина вечерни для URL-даты a (Y-m-d).
 * $readers — карта «нормализованная ссылка отрывка => имя чтеца»; имя выводится
 * перед соответствующим отрывком чтения. $readerAvatars — карта «ключ => URL
 * аватара чтеца» (необязательно).
 */
function vespers_text_html(string $dateA, array $readers = [], array $readerAvatars = []): string
{
    $readerLine = function (string $key) use ($readers, $readerAvatars): string {
        return vespers_reader_line($readers, $key, $readerAvatars);
    };
    $W = date('Y-m-d', strtotime($dateA . ' +1 day'));
    $dayA = vespers_day($dateA);
    $dayW = vespers_day($W);
    $partsW = vespers_parts($W);

    $dowA = (int)date('w', strtotime($dateA)); // 0=воскресенье
    $dowW = (int)date('w', strtotime($W));
    $glas = (int)($dayW['glas'] ?? 0);
    if ($glas < 1 || $glas > 8) {
        $glas = 1;
    }

    $mdW = date('n-j', strtotime($W)); // месяц-день W
    $isCross = $mdW === '9-27';        // Воздвижение
    $isTransfig = $mdW === '8-19';     // Преображение
    $skipPrayers = ($isCross || $isTransfig) && $dowA !== 0 && $dowA !== 6;

    $isHoliday = $dowA === 6 || array_key_exists('vespers', $partsW);

    $out = '';

    $out .= vespers_frag('head_start') . "\n";

    // isHoliday-блок
    if ($isHoliday) {
        $out .= $readerLine('priidite') . vespers_frag('priidite') . "\n";
        $out .= $readerLine('psalm103') . vespers_frag('psalm103') . "\n";
        $out .= vespers_frag('velikaja_ektenia') . "\n";
    }

    // Молитвы
    if (!$skipPrayers) {
        if ($isHoliday || $dowA === 1) $out .= $readerLine('prayer1') . vespers_frag('prayer1') . "\n";
        if ($isHoliday) $out .= $readerLine('kafisma') . vespers_frag('kafisma') . "\n";
        if ($isHoliday || $dowA === 2) $out .= $readerLine('prayer2') . vespers_frag('prayer2') . "\n";
        if ($isHoliday || $dowA === 3) $out .= $readerLine('prayer3') . vespers_frag('prayer3') . "\n";
        if ($isHoliday || $dowA === 4) $out .= $readerLine('prayer4') . vespers_frag('prayer4') . "\n";
        if ($isHoliday || $dowA === 5) $out .= $readerLine('prayer5') . vespers_frag('prayer5') . "\n";
        if ($isHoliday) $out .= $readerLine('prayer6') . vespers_frag('prayer6') . "\n";
        if ($isHoliday || $dowA === 0) $out .= $readerLine('prayer7') . vespers_frag('prayer7') . "\n";
    }

    $out .= $readerLine('prayer8') . vespers_frag('prayer8') . "\n";
    $out .= $readerLine('vechernie_psalmi') . vespers_frag('vechernie_psalmi') . "\n";

    // Стихиры на «Господи, взываю»
    $out .= '<h3 class="H3">Стихиры на «Господи, взываю»</h3>' . "\n";
    $gv = vespers_parts_block($partsW, 'vespers.Стихиры на Господи взываю');
    $gvHtml = '';
    foreach ($gv['values'] as $v) {
        $gvHtml .= vespers_clean($v) . "\n";
    }
    if ($gv['values'] && $gv['replace']) {
        $out .= $gvHtml;
    } elseif ($dowW === 0) {
        $out .= vespers_frag('stihiri_gv_glas' . $glas) . "\n";
    } elseif ($gvHtml !== '') {
        $out .= $gvHtml;
    }

    // Вход
    $out .= vespers_frag('vhod') . "\n";
    $out .= vespers_frag('jasni_svet') . "\n";

    // Чтения
    $out .= '<h2 class="H2">Чтения</h2>' . "\n";
    $prok = vespers_parts_block($partsW, 'vespers.Прокимен вечерни');
    $out .= $readerLine('prokimen');
    if ($prok['values']) {
        $out .= vespers_clean(implode("\n", $prok['values'])) . "\n";
    } else {
        $idx = $dowW === 0 ? 6 : $dowW - 1;
        $out .= vespers_frag('prokimen' . $idx) . "\n";
    }
    $out .= '<span id="reading"></span>' . "\n";
    $out .= '<p class="P"><span class="Red">П</span> Премудрость!</p>' . "\n";
    $out .= '<p class="P"><span class="Red">Ч</span> Из книги… чтение.</p>' . "\n";
    $out .= '<p class="P"><span class="Red">П</span> Будем внимательны!</p>' . "\n";
    $out .= '<br>' . "\n";
    $readingHtml = '';
    foreach (vespers_reading_passages($dayA) as $p) {
        $readingHtml .= $readerLine('reading:' . $p['key']);
        $readingHtml .= vespers_reading_html($dateA, $p['label'], $p['ref']);
    }
    if ($readingHtml !== '') {
        $out .= $readingHtml;
    }

    $out .= $readerLine('sermon') . '<h3 class="H3">Проповедь</h3>' . "\n";

    // Ектения об оглашаемых
    $out .= vespers_frag('ektenia_oglash') . "\n";
    $out .= $readerLine('prayer_katekhumens') . vespers_frag('prayer_katekhumens') . "\n";
    $out .= '<p class="P"><span class="Red">П</span> Оглашаемые, отступи́те!</p>' . "\n";

    $out .= vespers_frag('sugubaja') . "\n";
    $out .= $readerLine('udostoi') . vespers_frag('udostoi') . "\n";

    // Заключение
    $out .= '<h2 class="H2">Заключение</h2>' . "\n";
    $out .= '<p class="P"><span class="Red">П</span> Завершим нашу вечернюю молитву Господу!</p>' . "\n";
    $out .= $readerLine('prayer9') . vespers_frag('prayer9') . "\n";
    $out .= '<br>' . "\n";
    $out .= $readerLine('prayer_glavopriklonnaja') . vespers_frag('prayer_glavopriklonnaja') . "\n";

    // Стихиры «на стихах»
    $nstih = vespers_parts_block($partsW, 'vespers.Стихиры на стихах');
    $nstihHtml = '';
    foreach ($nstih['values'] as $v) {
        $nstihHtml .= vespers_clean($v) . "\n";
    }
    $nstihOut = '';
    if ($nstih['values'] && $nstih['replace']) {
        $nstihOut = $nstihHtml;
    } elseif ($dowW === 0) {
        $nstihOut = vespers_frag('stihiri_stih_glas' . $glas) . "\n";
    }
    if (trim($nstihOut) !== '') {
        $out .= '<h3 class="H3">Стихиры «на стихах»</h3>' . "\n";
        $out .= $nstihOut;
    }

    // Ныне отпускаешь + Трисвятое
    $out .= $readerLine('prayer_simeona') . vespers_frag('prayer_simeona') . "\n";
    $out .= $readerLine('strisvatogo') . vespers_frag('strisvatogo') . "\n";

    // Тропари
    $out .= '<h3 class="H3">Тропари</h3>' . "\n";
    $trop = vespers_parts_block($partsW, 'shared.Тропари');
    $tropHtml = '';
    foreach ($trop['values'] as $v) {
        $tropHtml .= vespers_clean($v) . "\n";
    }
    if ($tropHtml !== '') {
        if ($dowW === 0 && !$trop['replace']) {
            $out .= vespers_frag('glas' . $glas) . "\n";
        }
        $out .= $tropHtml;
    } else {
        if ($dowW === 0) {
            $out .= vespers_frag('glas' . $glas) . "\n";
        } else {
            $out .= vespers_frag('day' . $dowW) . "\n";
        }
    }

    // Отпуст
    $out .= vespers_frag('ending_static') . "\n";
    $otp = vespers_parts_block($partsW, 'shared.Отпуст Синаксарный');
    $otpHtml = '';
    foreach ($otp['values'] as $v) {
        $otpHtml .= vespers_clean($v) . "\n";
    }
    if ($otpHtml === '') {
        $otpHtml = vespers_frag('otpust') . "\n";
    }
    $out .= '<h3 class="H3">Отпуст</h3>' . "\n" . $otpHtml;

    // Святые дня
    $saints = (string)($dayW['saints'] ?? '');
    if ($saints !== '') {
        $out .= '<h2 class="H2">Святые дня</h2>' . "\n";
        $out .= '<div>' . vespers_clean_saints($saints) . '</div>' . "\n";
        $out .= '<br>' . "\n";
        $out .= vespers_frag('tverdina') . "\n";
    }

    // Кондаки и величания
    $kond = vespers_parts_block($partsW, 'shared.Кондаки');
    $kondHtml = '';
    foreach ($kond['values'] as $v) {
        $kondHtml .= vespers_clean($v) . "\n";
    }
    if ($kondHtml !== '') {
        if ($dowW === 0 && !$kond['replace']) {
            $out .= vespers_frag('kondak_glas' . $glas) . "\n";
        }
        $out .= $kondHtml;
    } else {
        if ($dowW === 0) {
            $out .= vespers_frag('kondak_glas' . $glas) . "\n";
        } else {
            $out .= vespers_frag('kondak_day' . $dowW) . "\n";
        }
    }

    $vel = vespers_parts_block($partsW, 'shared.Величания');
    foreach ($vel['values'] as $v) {
        $out .= vespers_clean($v) . "\n";
    }

    return $out;
}