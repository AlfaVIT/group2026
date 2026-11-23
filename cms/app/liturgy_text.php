<?php

/**
 * Локальный текст Литургии Иоанна Златоуста (воскресный день).
 *
 * Постоянный чин хранится в шаблоне templates/default/partials/liturgy/
 * zlatoust-sunday.html — он выгружен из приложения molitva.app и очищен от
 * интерфейса (аудио, кнопки, ссылки). Изменяемые части подставляются из API
 * того же приложения: тропари и кондаки, прокимен и аллилуарий по гласу,
 * причастен, задостойник, входной стих, отпуст, Апостол и Евангелие.
 *
 * Особые дни (Великий пост, Пасха, Рождество, Богоявление, праздники с
 * особыми частями чина) здесь не генерируются — для них страница показывает
 * текст приложения во встроенном окне, чтобы не ошибиться в чине.
 */

/** Прокимены и аллилуарии по гласам (см. app/liturgy_tones.php). */
function liturgy_tones(): array
{
    static $tones = null;
    if ($tones === null) {
        $tones = require __DIR__ . '/liturgy_tones.php';
    }
    return $tones;
}

function liturgy_template_path(): string
{
    return BASE_PATH . '/templates/' . theme_dir() . '/partials/liturgy/zlatoust-sunday.html';
}

/**
 * Можно ли собрать текст локально: только обычное воскресенье, без особых
 * частей чина и вне периодов, где служится другой чин.
 */
function liturgy_can_local(string $date): bool
{
    $ts = strtotime($date);
    if ($ts === false || (int)date('w', $ts) !== 0) {
        return false;
    }
    if (!is_file(liturgy_template_path())) {
        return false;
    }

    // без данных календаря и чтений локальный текст будет неполным
    $day = vespers_day($date);
    if (empty($day['glas']) || empty($day['readings']['Литургия'])) {
        return false;
    }

    // Великий пост, Страстная и Светлая седмицы — другие чины
    $offset = vespers_offset_days($date);
    if ($offset >= -48 && $offset <= 7) {
        return false;
    }

    // Рождество, Богоявление и ближайшие воскресенья — особые антифоны и прокимены
    $md = date('m-d', $ts);
    if ($md >= '12-25' || $md <= '01-19') {
        return false;
    }

    // праздничные вставки, которых нет в обычном воскресном чине
    $parts = vespers_parts($date);
    foreach (['Задостойник', 'Входной стих', 'Вместо Трисвятого', 'Отпуст'] as $key) {
        if (!empty($parts['liturgy'][$key])) {
            return false;
        }
    }
    return true;
}

/** HTML одной изменяемой части из parts (с санитайзом). */
function liturgy_part_html(array $parts, string $path): string
{
    $values = vespers_parts_filtered($parts, $path, 'Литургия');
    $out = '';
    foreach ($values as $value) {
        $clean = vespers_clean($value, true);
        if ($clean !== '') {
            $out .= $clean;
        }
    }
    return $out;
}

/**
 * Чтения Литургии из API. Возвращает три блока: Апостол, Евангелие и
 * дополнительные чтения дня (например, Богородице) — их приложение тоже
 * показывает, но основная последовательность чина идёт по первому набору.
 *
 * @return array{apostle: string, gospel: string, extra: string}
 */
function liturgy_readings_bundle(string $date): array
{
    $day = vespers_day($date);
    $sets = $day['readings']['Литургия'] ?? [];
    if (!is_array($sets) || !$sets) {
        return ['apostle' => '', 'gospel' => '', 'extra' => ''];
    }
    $labels = array_keys($sets);
    $firstLabel = (string)$labels[0];
    $first = array_values((array)$sets[$firstLabel]);
    $apostleRef = (string)($first[0] ?? '');
    $gospelRef = (string)($first[1] ?? '');

    $apostle = $apostleRef !== ''
        ? '<h3 class="H3">Чтение Апостола</h3>' . vespers_reading_html($date, '', $apostleRef)
        : '';
    $gospel = $gospelRef !== ''
        ? '<h3 class="H3">Чтение Евангелия</h3>' . vespers_reading_html($date, '', $gospelRef)
        : '';

    $extra = '';
    foreach (array_slice($labels, 1) as $label) {
        $refs = array_values((array)$sets[$label]);
        foreach ([['Апостол', $refs[0] ?? ''], ['Евангелие', $refs[1] ?? '']] as [$kind, $ref]) {
            $ref = (string)$ref;
            if ($ref === '') {
                continue;
            }
            $extra .= '<h3 class="H3">' . ($kind === 'Евангелие' ? 'Чтение Евангелия' : 'Чтение Апостола')
                . ' (' . e((string)$label) . ')</h3>'
                . vespers_reading_html($date, '', $ref);
        }
    }
    return ['apostle' => $apostle, 'gospel' => $gospel, 'extra' => $extra];
}

/** Подставляет изменяемые части в шаблон по маркерам data-lit. */
function liturgy_fill_markers(string $template, array $map): string
{
    if ($template === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><html><body><div id="lit-root">' . $template . '</div></body></html>',
        LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    $xp = new DOMXPath($doc);

    foreach ($map as $key => $html) {
        if ($html === '') {
            continue; // остаётся текст по умолчанию из шаблона
        }
        $frag = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $frag->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $body = $frag->getElementsByTagName('body')->item(0);
        if (!$body) {
            continue;
        }
        foreach ($xp->query('//*[@data-lit="' . $key . '"]') as $node) {
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            foreach (iterator_to_array($body->childNodes) as $child) {
                $node->appendChild($doc->importNode($child, true));
            }
        }
    }

    $root = $xp->query('//div[@id="lit-root"]')->item(0);
    if (!$root) {
        return '';
    }
    // маркеры больше не нужны — оставляем только текст
    foreach ($xp->query('//*[@data-lit]') as $node) {
        $node->removeAttribute('data-lit');
    }
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

/**
 * Собирает HTML текста Литургии для даты. Возвращает '' если для этой даты
 * локальная генерация не предусмотрена.
 */
function liturgy_text_html(string $date): string
{
    if (!liturgy_can_local($date)) {
        return '';
    }
    $template = @file_get_contents(liturgy_template_path());
    if ($template === false) {
        return '';
    }

    $day = vespers_day($date);
    $parts = vespers_parts($date);
    $glas = (int)($day['glas'] ?? 0);
    $tones = liturgy_tones();
    $tone = $tones[$glas] ?? [];

    $prokimen = (string)($tone['prokimen'] ?? '');
    $alleluia = (string)($tone['alleluia'] ?? '');
    $prokimen .= liturgy_part_html($parts, 'liturgy.Прокимен');
    $alleluia .= liturgy_part_html($parts, 'liturgy.Аллилуарий');

    // тропари и кондак: в обычные воскресенья их в API нет — берём по гласу
    $troparia = liturgy_part_html($parts, 'shared.Тропари');
    if ($troparia === '') {
        $troparia = (string)($tone['troparia'] ?? '');
    }
    $kondak = liturgy_part_html($parts, 'shared.Кондаки');
    if ($kondak === '') {
        $kondak = (string)($tone['kondak'] ?? '');
    }

    $bundle = liturgy_readings_bundle($date);
    $readings = $prokimen
        . $bundle['apostle']
        . $alleluia
        . $bundle['gospel']
        . $bundle['extra'];

    return liturgy_fill_markers($template, [
        'troparia'    => $troparia,
        'kondak'      => $kondak,
        'readings'    => $readings,
        'prichasten'  => liturgy_part_html($parts, 'liturgy.Причастен'),
        'zadostoinik' => liturgy_part_html($parts, 'liturgy.Задостойник'),
        'vhod'        => liturgy_part_html($parts, 'liturgy.Входной стих'),
        'otpust'      => liturgy_part_html($parts, 'liturgy.Отпуст'),
    ]);
}
