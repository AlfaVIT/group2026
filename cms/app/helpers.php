<?php

const ROLES = ['Старший', 'Помощник', 'Казначей', 'СестраБрат', 'Гость'];
const MEETING_ROLES = ['СтаршийВстречи', 'СтаршийПоМолитве', 'ПомощникПоОрганизации', 'ПомощникПоПитанию'];
const ATTENDANCE_STATUSES = [
    'present' => 'Буду присутствовать',
    'online'  => 'Буду онлайн',
    'absent'  => 'Буду отсутствовать',
];
const ATTENDANCE_ROLES = ['Старший', 'Помощник', 'Казначей', 'СестраБрат'];
const ADDRESS_TERMS = ['брат', 'сестра'];
const MEETING_TYPE_PRESETS = ['Встреча группы', 'Агапа'];
const EXPENSE_ARTICLES = ['Трапеза', 'Агапа', 'Прочее'];
const MEETING_CHECKLIST = [
    'Помещение' => [
        'chairs'        => 'нужное количество стульев',
        'tables'        => 'столы',
        'icon'          => 'икона',
        'scripture'     => 'писание',
    ],
    'Трапеза' => [
        'water'         => 'питьевая вода',
        'kettle'        => 'ёмкость, в которой кипятить воду (чайник или термопот)',
        'tea'           => 'чай',
        'main_food'     => 'основная еда',
        'fruit'         => 'фрукты',
        'sweets'        => 'сладкое к чаю',
        'bread'         => 'хлеб',
        'plates'        => 'одноразовые тарелки',
        'cutlery'       => 'вилки / ложки / ножи',
        'napkins'       => 'салфетки',
        'cutting_board' => 'разделочная доска',
        'dishes'        => 'блюда / подносы, чтобы всё разложить',
        'trash_bags'    => 'мусорные пакеты',
    ],
];

const PERMISSIONS = [
    'manage_users'     => ['Старший'],
    'manage_places'    => ['Старший', 'Помощник'],
    'manage_meetings'  => ['Старший', 'Помощник'],
    'manage_settings'  => ['Старший'],
    'mark_attendance'  => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
    'view_attendance'  => ['Старший', 'Помощник'],
    'assign_roles'     => ['Старший', 'Помощник'],
    'assign_readers'   => ['Старший', 'Помощник'],
    'send_broadcast'   => ['Старший', 'Помощник'],
    'add_expense'      => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
    'view_expenses'    => ['Старший', 'Казначей'],
    'view_treasury'    => ['Казначей'],
    'view_treasury_graph' => ['Старший'],
    'manage_materials' => ['Старший', 'Помощник'],
    'manage_articles'  => ['Старший', 'Казначей'],
    'write_notes'      => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
    'view_stats'       => ['Старший', 'Помощник'],
    // персональные данные участников (контакты, дни рождения) не показываются «Гостю»
    'view_pd'          => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
];

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Превращает ссылки в тексте в кликабельные, сохраняя экранирование.
 * Использовать вместе с nl2br(): nl2br(linkify($text)).
 */
function linkify(string $text): string
{
    $safe = e($text);
    return preg_replace_callback(
        '~https?://[^\s<"]+~iu',
        static fn(array $m): string => '<a href="' . $m[0] . '" target="_blank" rel="noopener nofollow">' . $m[0] . '</a>',
        $safe
    ) ?? $safe;
}

/** Человекочитаемый размер файла: 512 Б, 15 КБ, 2.4 МБ. */
function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' МБ';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' КБ';
    }
    return $bytes . ' Б';
}

/** Разрешённые теги в конспекте встречи. */
const NOTE_HTML_TAGS = ['p', 'div', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del',
    'ul', 'ol', 'li', 'blockquote', 'h3', 'h4', 'a', 'code'];

/**
 * Очищает HTML конспекта: оставляет только безопасные теги, у ссылок —
 * только http(s)/mailto. Всё остальное разворачивается в текст.
 */
function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html)));
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>', LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= cms_sanitize_node($child);
    }
    return trim($out);
}

/** Рекурсивная очистка одного узла (см. sanitize_html). */
function cms_sanitize_node(DOMNode $node, bool $inLink = false): string
{
    if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
        // обычный текст: экранируем и превращаем голые ссылки в кликабельные;
        // внутри <a> этого не делаем, чтобы не получить вложенные ссылки
        return $inLink ? e($node->nodeValue) : linkify((string)$node->nodeValue);
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return '';
    }
    $tag = strtolower($node->nodeName);
    $inner = '';
    foreach (iterator_to_array($node->childNodes) as $child) {
        $inner .= cms_sanitize_node($child, $tag === 'a' || $inLink);
    }
    if (!in_array($tag, NOTE_HTML_TAGS, true)) {
        return $inner; // неизвестный тег выкидываем, содержимое оставляем
    }
    if ($tag === 'br') {
        return '<br>';
    }
    $attrs = '';
    if ($tag === 'a') {
        $href = trim($node->getAttribute('href'));
        if (!preg_match('~^(https?://|mailto:|/|#)~i', $href)) {
            return $inner; // опасную ссылку разворачиваем в текст
        }
        $attrs = ' href="' . e($href) . '" target="_blank" rel="noopener nofollow"';
    }
    return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
}

/** HTML конспекта для вывода: новый формат — очищенный HTML, старый — текст. */
function note_html(array $note): string
{
    return format_content((string)($note['body'] ?? ''), (string)($note['format'] ?? 'text'));
}

/** HTML материала для вывода (то же правило: html или старый текст). */
function material_html(array $material): string
{
    return format_content((string)($material['content'] ?? ''), (string)($material['body_format'] ?? 'text'));
}

/** Текст, сохранённый редактором (html), или старый простой текст — в HTML для вывода. */
function format_content(string $body, string $format = 'text'): string
{
    if ($format === 'html') {
        return sanitize_html($body);
    }
    return nl2br(linkify($body));
}

/** Пустой ли конспект (без учёта разметки и пробелов). */
function note_is_empty(string $body, string $format = 'text'): bool
{
    if ($format !== 'html') {
        return trim($body) === '';
    }
    $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $text);
    return trim($text) === '';
}

/**
 * Встроенная SVG-иконка (feather, stroke = currentColor).
 * Неизвестное имя возвращает пустую строку — шаблон не ломается.
 */
function ui_icon(string $name, int $size = 16): string
{
    $paths = [
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="19"/><line x1="9.5" y1="16.5" x2="14.5" y2="16.5"/>',
        'video'    => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'mic'      => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',
        'stop'     => '<rect x="6" y="6" width="12" height="12" rx="2"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="cms-icon" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/**
 * Принимает «логин или email». Если введён email — логин берётся из части до «@»,
 * email заполняется. Если введён логин — email остаётся пустым.
 */
function resolve_login_email(string $value): array
{
    $value = mb_strtolower(trim($value));
    if ($value === '') {
        return ['email' => '', 'login' => '', 'error' => 'Укажите логин или email'];
    }
    if (strpos($value, '@') !== false) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => '', 'login' => '', 'error' => 'Укажите корректный email'];
        }
        return ['email' => $value, 'login' => explode('@', $value, 2)[0], 'error' => null];
    }
    if (preg_match('/[\s@]/', $value)) {
        return ['email' => '', 'login' => '', 'error' => 'Логин не должен содержать пробелы и символ @'];
    }
    if (mb_strlen($value) < 3) {
        return ['email' => '', 'login' => '', 'error' => 'Логин должен быть не короче 3 символов'];
    }
    return ['email' => '', 'login' => $value, 'error' => null];
}

function user_font_size(?array $user = null): int
{
    $user ??= Auth::user();
    $size = (int)($user['font_size'] ?? 100);
    $size = (int)round($size / FONT_SIZE_STEP) * FONT_SIZE_STEP;
    return max(FONT_SIZE_MIN, min(FONT_SIZE_MAX, $size));
}

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    }
    return $base;
}

function url(string $page, array $params = []): string
{
    $params['p'] = $page;
    return base_url() . '/index.php?' . http_build_query($params);
}

function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/**
 * Сравнивает два URL по пути и строке запроса (для определения «та же страница»).
 */
function page_url_equal(string $a, string $b): bool
{
    $pa = parse_url($a);
    $pb = parse_url($b);
    if ($pa === false || $pb === false) {
        return false;
    }
    if (($pa['path'] ?? '') !== ($pb['path'] ?? '')) {
        return false;
    }
    $qa = $pa['query'] ?? '';
    $qb = $pb['query'] ?? '';
    if ($qa === $qb) {
        return true;
    }
    parse_str($qa, $aa);
    parse_str($qb, $ab);
    ksort($aa);
    ksort($ab);
    return $aa === $ab;
}

function redirect(string $url): void
{
    if (is_ajax()) {
        $flash = get_flash();
        $samePage = page_url_equal($url, $_SERVER['HTTP_REFERER'] ?? '');
        if (!$samePage && $flash) {
            $_SESSION['flash'] = $flash;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'redirect' => $url,
            'navigate' => !$samePage,
            'toast' => $samePage ? $flash : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $url);
    exit;
}

function input(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Ставит/снимает cookie-подсказку «браузер дал согласие на обработку персональных
 * данных» — используется для предзаполненного чекбокса на форме входа.
 */
function pd_consent_cookie(bool $on): void
{
    $p = session_get_cookie_params();
    setcookie('cms_pd_consent', $on ? '1' : '', [
        'expires' => $on ? time() + 365 * 86400 : time() - 3600,
        'path' => $p['path'],
        'domain' => $p['domain'],
        'secure' => $p['secure'],
        'httponly' => false,
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

function pd_consent_cookie_on(): bool
{
    return ($_COOKIE['cms_pd_consent'] ?? '') === '1';
}

/**
 * Хост текущего запроса без порта, в нижнем регистре.
 * HTTP_HOST может содержать порт (например, на локальном сервере),
 * а parse_url(HTTP_REFERER, PHP_URL_HOST) — нет; иначе сравнение не работает.
 */
function request_host(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }
    $parsed = (string)(parse_url('http://' . $host, PHP_URL_HOST) ?: '');
    return strtolower($parsed !== '' ? $parsed : $host);
}

/** Хост из Referer без порта ('' если заголовка нет). */
function referer_host(): string
{
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return '';
    }
    return strtolower((string)(parse_url($ref, PHP_URL_HOST) ?: ''));
}

function csrf_check(): void
{
    if (hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        return;
    }
    // Метка не совпала: сессия могла быть пересоздана (например, после смены версии
    // PHP), поэтому старый php-файл сессии утрачен. Обновляем метку и возвращаем
    // пользователя на страницу, чтобы он повторил действие — вместо жёсткого 419.
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    if (is_ajax()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Защитная метка формы устарела. Страница будет обновлена.',
            'reload' => true,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    set_flash('Защитная метка формы устарела. Повторите действие.', 'warning');
    $back = url('home');
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refHost = referer_host();
        $selfHost = request_host();
        if ($refHost !== '' && $selfHost !== '' && $refHost === $selfHost) {
            $back = $_SERVER['HTTP_REFERER'];
        }
    }
    redirect($back);
}

function money($amount): string
{
    return number_format((float)$amount, 0, ',', ' ') . ' ₽';
}

/** Статья расхода по типу встречи: Встреча группы → Трапеза, Агапа → Агапа, иначе Прочее. */
function expense_article_for_meeting_type(string $meetingType): string
{
    $t = trim($meetingType);
    if ($t === 'Встреча группы') {
        return 'Трапеза';
    }
    if ($t === 'Агапа') {
        return 'Агапа';
    }
    return 'Прочее';
}

/** Человекочитаемое устройство по User-Agent: «Браузер · ОС». */
function visitor_device(string $ua): string
{
    $ua = trim($ua);
    if ($ua === '') {
        return 'Неизвестно';
    }
    $os = 'другое';
    foreach ([
        'Windows' => '/Windows/i',
        'Android' => '/Android/i',
        'iOS' => '/iPhone|iPad|iPod/i',
        'macOS' => '/Macintosh|Mac OS X/i',
        'Linux' => '/Linux/i',
    ] as $name => $re) {
        if (preg_match($re, $ua)) {
            $os = $name;
            break;
        }
    }
    $browser = 'другое';
    foreach ([
        'Yandex' => '/YaBrowser/i',
        'Edge' => '/Edg\//i',
        'Opera' => '/OPR\/|Opera/i',
        'Chrome' => '/Chrome\//i',
        'Firefox' => '/Firefox\//i',
        'Safari' => '/Safari\//i',
    ] as $name => $re) {
        if (preg_match($re, $ua)) {
            $browser = $name;
            break;
        }
    }
    return $browser . ' · ' . $os;
}

/**
 * Записывает вход пользователя. Хранит последние 50 записей КАЖДОГО участника,
 * иначе при 20 участниках история вытесняется за пару дней.
 */
function log_visit(int $userId): void
{
    $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim(explode(',', $ip)[0]);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    Db::insert('visits', [
        'user_id' => $userId,
        'ip' => $ip,
        'user_agent' => $ua,
        // SQLite datetime('now') — это UTC, а сайт живёт в Europe/Moscow;
        // «последний вход» в статистике должен показывать местное время
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    Db::q(
        'DELETE FROM visits
         WHERE user_id = ?
           AND id NOT IN (SELECT id FROM visits WHERE user_id = ? ORDER BY id DESC LIMIT 50)',
        [$userId, $userId]
    );
}

function rdate(string $date): string
{
    static $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    static $weekdays = [1 => 'понедельник', 'вторник', 'среда', 'четверг',
        'пятница', 'суббота', 'воскресенье'];
    $ts = strtotime($date);
    if ($ts === false) {
        return e($date);
    }
    $d = (int)date('j', $ts);
    $m = $months[(int)date('n', $ts)];
    $y = (int)date('Y', $ts);
    $w = $weekdays[(int)date('N', $ts)];
    $year = $y === (int)date('Y') ? '' : ' ' . $y . ' года';
    return $w . ', ' . $d . ' ' . $m . $year;
}

function today(): string
{
    return date('Y-m-d');
}

function plural_ru(int $n, string $one, string $few, string $many): string
{
    $d = $n % 10;
    $h = $n % 100;
    if ($h >= 11 && $h <= 14) {
        return $many;
    }
    if ($d === 1) {
        return $one;
    }
    if ($d >= 2 && $d <= 4) {
        return $few;
    }
    return $many;
}

function age_word(int $n): string
{
    $d = $n % 10;
    $h = $n % 100;
    if ($h >= 11 && $h <= 14) {
        return 'лет';
    }
    if ($d === 1) {
        return 'год';
    }
    if ($d >= 2 && $d <= 4) {
        return 'года';
    }
    return 'лет';
}

function full_name(array $user): string
{
    return $user['address_term'] . ' ' . $user['name'];
}

/**
 * Имя участника как «живая» ссылка: по клику открывается карточка участника
 * поверх страницы (модальное окно). Без id возвращает обычный текст.
 *
 * $tag = 'span' — для мест, где ссылку вкладывать нельзя (внутри кнопки):
 * такой элемент получает role="link" и открывает карточку по клику/Enter.
 */
function user_link(array $user, string $tag = 'a', string $class = '', ?string $text = null): string
{
    $name = e($text ?? full_name($user));
    $id = (int)($user['id'] ?? 0);
    if ($id <= 0) {
        return $name;
    }
    $cls = 'cms-user-link' . ($class !== '' ? ' ' . $class : '');
    if ($tag === 'span') {
        return '<span class="' . $cls . '" role="link" tabindex="0" data-user-card="' . $id . '">'
            . $name . '</span>';
    }
    return '<a class="' . $cls . '" href="' . e(url('user_card', ['id' => $id])) . '"'
        . ' data-user-card="' . $id . '">' . $name . '</a>';
}

/**
 * URL аватара пользователя (файл в uploads/avatars). Пусто, если аватар не задан
 * или файл отсутствует.
 */
function avatar_url(array $user): string
{
    $file = trim((string)($user['avatar'] ?? ''));
    if ($file === '') {
        return '';
    }
    $path = UPLOAD_DIR . '/avatars/' . $file;
    return is_file($path) ? base_url() . '/' . UPLOAD_URL . '/avatars/' . rawurlencode($file) : '';
}

/**
 * HTML маленького круглого аватара рядом с именем. Пусто, если аватара нет.
 */
function avatar_img(array $user, int $size = 28): string
{
    $url = avatar_url($user);
    if ($url === '') {
        return '';
    }
    return '<img class="cms-avatar" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;"'
        . ' src="' . e($url) . '" alt="' . e(full_name($user)) . '" loading="lazy">';
}

/**
 * Аватар места встречи: фотография хозяина (его аватар), если он задан,
 * иначе первая фотография самого места. Пусто, если нет ни того, ни другого.
 */
function place_avatar_url(array $place): string
{
    $ownerAvatar = trim((string)($place['owner_avatar'] ?? ''));
    if ($ownerAvatar !== '' && is_file(UPLOAD_DIR . '/avatars/' . $ownerAvatar)) {
        return base_url() . '/' . UPLOAD_URL . '/avatars/' . rawurlencode($ownerAvatar);
    }
    $photo = trim((string)($place['first_photo'] ?? ''));
    if ($photo !== '' && is_file(UPLOAD_DIR . '/' . $photo)) {
        return base_url() . '/' . UPLOAD_URL . '/' . rawurlencode($photo);
    }
    return '';
}

/**
 * Приводит введённую дату рождения к формату YYYY-MM-DD.
 * Год можно не указывать: тогда сохраняется 0000-MM-DD, возраст не выводится.
 * Принимает ДД.ММ.ГГГГ, ДД.ММ или уже сохранённый YYYY-MM-DD.
 */
function parse_birthday(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : '';
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
        return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : '';
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $raw, $m)) {
        return checkdate((int)$m[2], (int)$m[1], 2000) ? sprintf('0000-%02d-%02d', $m[2], $m[1]) : '';
    }
    return '';
}

/**
 * Отображает дату рождения в форме для ввода: ДД.ММ.ГГГГ или ДД.ММ без года.
 */
function birthday_display(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $stored, $m)) {
        if ($m[1] === '0000') {
            return sprintf('%02d.%02d', $m[3], $m[2]);
        }
        return sprintf('%02d.%02d.%04d', $m[3], $m[2], $m[1]);
    }
    return $stored;
}

/**
 * Есть ли в дате рождения год (иначе возраст не выводится).
 */
function birthday_has_year(string $stored): bool
{
    return $stored !== '' && strncmp($stored, '0000-', 5) !== 0;
}

/** Разбирает сохранённую дату (YYYY-MM-DD или 0000-MM-DD) на части для трёх полей ввода. */
function birthday_parts(string $stored): array
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($stored), $m)) {
        return ['day' => '', 'month' => '', 'year' => ''];
    }
    return [
        'day' => ltrim($m[3], '0'),
        'month' => ltrim($m[2], '0'),
        'year' => $m[1] === '0000' ? '' : $m[1],
    ];
}

/** Ранее введённые годы рождения участников (для datalist поля ГГГГ). */
function birthday_year_options(): array
{
    return array_map('strval', array_column(
        Db::fetchAll(
            "SELECT DISTINCT substr(birthday, 1, 4) AS y FROM users
             WHERE birthday != '' AND substr(birthday, 1, 4) != '0000' ORDER BY y DESC"
        ),
        'y'
    ));
}

/**
 * Собирает дату рождения из трёх полей ввода (день, месяц, год).
 * Возвращает '' если поле не заполнено; YYYY-MM-DD или 0000-MM-DD (без года).
 * При ошибке заполняет $error и возвращает ''.
 */
function build_birthday(string $day, string $month, string $year, bool $noYear, ?string &$error = null): string
{
    $day = trim($day);
    $month = trim($month);
    $year = trim($year);
    if ($day === '' && $month === '' && $year === '') {
        return '';
    }
    if (!ctype_digit($day) || !ctype_digit($month) || $day === '' || $month === '') {
        $error = 'Укажите день и месяц рождения числами';
        return '';
    }
    $d = (int)$day;
    $m = (int)$month;
    if ($d < 1 || $d > 31 || $m < 1 || $m > 12) {
        $error = 'Некорректный день или месяц рождения';
        return '';
    }
    if ($noYear) {
        if (!checkdate($m, $d, 2000)) {
            $error = 'Некорректная дата рождения (например, 30 февраля)';
            return '';
        }
        return sprintf('0000-%02d-%02d', $m, $d);
    }
    if (!ctype_digit($year) || mb_strlen($year) !== 4) {
        $error = 'Укажите год рождения (ГГГГ)';
        return '';
    }
    $y = (int)$year;
    if (!checkdate($m, $d, $y)) {
        $error = 'Некорректная дата рождения';
        return '';
    }
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function user_short(array $user): string
{
    $parts = preg_split('/\s+/u', trim($user['name']));
    $name = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
    return $user['address_term'] . ' ' . $name;
}

function role_badge_class(string $role): string
{
    return [
        'Старший'    => 'text-bg-primary',
        'Помощник'   => 'text-bg-success',
        'Казначей'   => 'text-bg-warning',
        'СестраБрат' => 'text-bg-secondary',
        'Гость'      => 'text-bg-light border',
    ][$role] ?? 'text-bg-secondary';
}

function attendance_eligible(): array
{
    $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
    return Db::fetchAll(
        "SELECT id, address_term, name, role FROM users
         WHERE is_active = 1 AND role IN ($ph) ORDER BY name",
        ATTENDANCE_ROLES
    );
}

function attendance_badge_class(string $status): string
{
    return [
        'present' => 'text-bg-success',
        'online'  => 'text-bg-info',
        'absent'  => 'text-bg-danger',
    ][$status] ?? 'text-bg-secondary';
}

function count_meeting_guests(string $guests): int
{
    $parts = array_map('trim', explode(',', $guests));
    return count(array_filter($parts, fn($g) => $g !== ''));
}

/**
 * Возвращает имена гостей встречи. Гости хранятся как список id учётных записей
 * с ролью «Гость»; для совместимости со старыми данными текстовые части
 * (не числа) сохраняются как есть.
 */
function meeting_guest_names(string $guests): array
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $guests)), fn($g) => $g !== ''));
    $ids = [];
    $names = [];
    foreach ($parts as $g) {
        if (ctype_digit($g)) {
            $ids[] = (int)$g;
        } else {
            $names[] = $g;
        }
    }
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $found = [];
        foreach (Db::fetchAll("SELECT id, address_term, name FROM users WHERE id IN ($ph)", $ids) as $u) {
            $found[(int)$u['id']] = full_name($u);
        }
        foreach ($ids as $id) {
            if (isset($found[$id])) {
                $names[] = $found[$id];
            }
        }
    }
    return $names;
}

/**
 * Число очных участников встречи: те, кто отметил очное участие (status = 'present').
 * Считаем по отметкам, а не по текущему is_active: явка — исторический факт,
 * и она не должна меняться при отключении учётной записи. Гости прибавляются отдельно.
 */
function present_count(int $meetingId): int
{
    $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
    return (int)(Db::value(
        "SELECT COUNT(*) FROM users
         WHERE role IN ($ph)
           AND EXISTS (SELECT 1 FROM attendance a
                       WHERE a.meeting_id = ? AND a.user_id = users.id
                         AND a.status = 'present')",
        array_merge(ATTENDANCE_ROLES, [$meetingId])
    ) ?? 0);
}

function short_role(string $role): string
{
    return role_label($role);
}

function role_label(string $role): string
{
    return [
        'СтаршийВстречи'          => 'Старший',
        'СтаршийПоМолитве'        => 'Молитва',
        'ПомощникПоОрганизации'   => 'Организация',
        'ПомощникПоПитанию'       => 'Питание',
    ][$role] ?? $role;
}

function sum_col(array $statuses): int
{
    return (int)array_sum($statuses);
}

function decode_materials(?string $json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Ключ статьи для сравнения: без учёта регистра и лишних пробелов.
 * SQLite lower() не понимает кириллицу, поэтому нормализация — в PHP.
 */
function article_key(string $name): string
{
    $name = (string)preg_replace('/\s+/u', ' ', trim($name));
    return mb_strtolower($name);
}

/** Каноническое написание статьи: без лишних пробелов, первая буква заглавная. */
function article_canonical(string $name): string
{
    $name = (string)preg_replace('/\s+/u', ' ', trim($name));
    if ($name === '') {
        return '';
    }
    // «ТРАПЕЗА» → «Трапеза», иначе первая буква заглавная, остальное как ввели
    if (mb_strtoupper($name) === $name) {
        $name = mb_strtolower($name);
    }
    return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
}

/**
 * Статьи из таблицы с объединением вариантов, различающихся регистром и пробелами.
 * Возвращает ['ключ' => 'отображаемое название'].
 */
function collect_articles(string $table, string $column): array
{
    static $cache = [];
    $cacheKey = $table . '.' . $column;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $rows = Db::fetchAll(
        "SELECT $column AS name, COUNT(*) AS cnt FROM $table
         WHERE TRIM($column) != '' GROUP BY $column ORDER BY cnt DESC, name"
    );
    $groups = [];
    foreach ($rows as $r) {
        $key = article_key((string)$r['name']);
        if ($key === '') {
            continue;
        }
        $groups[$key][(string)$r['name']] = (int)$r['cnt'];
    }
    $best = [];
    foreach ($groups as $key => $variants) {
        $picked = null;
        // предпочитаем вариант, который уже выглядит канонически
        foreach (array_keys($variants) as $spelling) {
            if (article_canonical($spelling) === $spelling) {
                $picked = $spelling;
                break;
            }
        }
        if ($picked === null) {
            arsort($variants);
            $picked = article_canonical((string)array_key_first($variants));
        }
        $best[$key] = $picked;
    }
    $cache[$cacheKey] = $best;
    return $best;
}

function address_parts(array $place, bool $withExtras = false): array
{
    $parts = [];
    if (!empty($place['region'])) {
        $parts[] = $place['region'];
    }
    if (!empty($place['district'])) {
        $parts[] = $place['district'];
    }
    if (!empty($place['locality'])) {
        $parts[] = $place['locality'];
    }
    $street = trim($place['street'] . ' ' . $place['house'] . ($place['house_letter'] ? '/' . $place['house_letter'] : ''));
    if (!empty($street)) {
        $parts[] = $street;
    }
    $extra = [];
    if (!empty($place['entrance'])) {
        $extra[] = 'подъезд ' . $place['entrance'];
    }
    if (!empty($place['floor'])) {
        $extra[] = 'этаж ' . $place['floor'];
    }
    if (!empty($place['apartment'])) {
        $extra[] = 'кв. ' . $place['apartment'];
    }
    if (!empty($place['intercom'])) {
        $extra[] = 'домофон ' . $place['intercom'];
    }
    if ($withExtras) {
        // квартира, этаж, подъезд, домофон — в общую строку адреса
        $parts = array_merge($parts, $extra);
    }
    return $parts;
}

function yandex_maps_link(array $place): string
{
    $q = implode(', ', address_parts($place));
    if ($q === '') {
        return '';
    }
    return 'https://yandex.ru/maps/?text=' . urlencode($q);
}

/** Свёртка длинной строки ICS по 75 октетов (без разрыва UTF-8). */
function ics_fold(string $line): string
{
    $out = '';
    $first = true;
    while ($line !== '') {
        $max = $first ? 75 : 74;
        if (strlen($line) <= $max) {
            $out .= ($first ? '' : ' ') . $line;
            break;
        }
        $cut = $max;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        if ($cut === 0) {
            $cut = $max;
        }
        $out .= ($first ? '' : ' ') . substr($line, 0, $cut) . "\r\n";
        $line = substr($line, $cut);
        $first = false;
    }
    return $out;
}

/**
 * Ссылка на трансляцию встречи: своя у встречи, иначе общая из настроек.
 */
function meeting_stream_url(array $meeting): string
{
    $own = trim((string)($meeting['stream_url'] ?? ''));
    if ($own !== '') {
        return $own;
    }
    return Setting::streamUrl();
}

/** Ссылка на трансляцию взята из общих настроек (у встречи своей нет). */
function meeting_stream_is_common(array $meeting): bool
{
    return trim((string)($meeting['stream_url'] ?? '')) === '' && Setting::streamUrl() !== '';
}

/** Формирует событие iCalendar для встречи (добавление в календарь устройства). */
function build_meeting_ics(array $meeting): string
{
    $esc = static fn(string $s): string => str_replace(
        ['\\', "\r\n", "\n", ';', ','],
        ['\\\\', '\\n', '\\n', '\\;', '\\,'],
        $s
    );

    $ts = strtotime((string)($meeting['date'] ?? ''));
    if ($ts === false) {
        $ts = time();
    }
    $start = date('Ymd\THis', $ts);
    $end = date('Ymd\THis', $ts + 2 * 3600);
    $stamp = gmdate('Ymd\THis\Z');
    $uid = 'meeting-' . (int)($meeting['id'] ?? 0) . '@group2026';

    $type = trim((string)($meeting['meeting_type'] ?? ''));
    $topic = trim((string)($meeting['topic'] ?? ''));
    $summary = $type !== '' ? $type : ($topic !== '' ? $topic : 'Встреча');

    $address = implode(', ', address_parts($meeting, true));

    $desc = [];
    if ($type !== '' && $topic !== '') {
        $desc[] = 'Тема: ' . $topic;
    }
    if (!empty($meeting['senior_name'])) {
        $desc[] = 'Старший на встрече: ' . $meeting['senior_name'];
    }
    if (!empty($meeting['stream_url'])) {
        $desc[] = 'Трансляция: ' . $meeting['stream_url'];
    }

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Group2026//Meeting//RU',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VTIMEZONE',
        'TZID:Europe/Moscow',
        'BEGIN:STANDARD',
        'DTSTART:19700101T000000',
        'TZOFFSETFROM:+0300',
        'TZOFFSETTO:+0300',
        'TZNAME:MSK',
        'END:STANDARD',
        'END:VTIMEZONE',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $stamp,
        'DTSTART;TZID=Europe/Moscow:' . $start,
        'DTEND;TZID=Europe/Moscow:' . $end,
        'SUMMARY:' . $esc($summary),
    ];
    if ($address !== '') {
        $lines[] = 'LOCATION:' . $esc($address);
    }
    if ($desc) {
        $lines[] = 'DESCRIPTION:' . $esc(implode("\n", $desc));
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    $out = '';
    foreach ($lines as $l) {
        $out .= ics_fold($l) . "\r\n";
    }
    return $out;
}

/** Ссылка «добавить в Google Календарь» (без файла) для встречи. */
function google_calendar_link(array $meeting): string
{
    $ts = strtotime((string)($meeting['date'] ?? ''));
    if ($ts === false) {
        return '';
    }
    $start = date('Ymd\THis', $ts);
    $end = date('Ymd\THis', $ts + 2 * 3600);

    $type = trim((string)($meeting['meeting_type'] ?? ''));
    $topic = trim((string)($meeting['topic'] ?? ''));
    $title = $type !== '' ? $type : ($topic !== '' ? $topic : 'Встреча');
    $address = implode(', ', address_parts($meeting, true));

    $details = [];
    if ($type !== '' && $topic !== '') {
        $details[] = 'Тема: ' . $topic;
    }
    if (!empty($meeting['senior_name'])) {
        $details[] = 'Старший на встрече: ' . $meeting['senior_name'];
    }
    if (!empty($meeting['stream_url'])) {
        $details[] = 'Трансляция: ' . $meeting['stream_url'];
    }

    $query = http_build_query([
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $start . '/' . $end,
        'ctz' => 'Europe/Moscow',
        'location' => $address,
        'details' => implode("\n", $details),
    ], '', '&', PHP_QUERY_RFC3986);

    return 'https://calendar.google.com/calendar/render?' . $query;
}

/**
 * Android intent-ссылка для добавления встречи в календарь по умолчанию
 * (ACTION_INSERT, нативное окно события). Если приложения нет — откат на веб-ссылку.
 */
function android_calendar_intent(array $meeting): string
{
    $ts = strtotime((string)($meeting['date'] ?? ''));
    if ($ts === false) {
        return '';
    }
    $begin = $ts * 1000;
    $end = ($ts + 2 * 3600) * 1000;

    $type = trim((string)($meeting['meeting_type'] ?? ''));
    $topic = trim((string)($meeting['topic'] ?? ''));
    $title = $type !== '' ? $type : ($topic !== '' ? $topic : 'Встреча');
    $location = implode(', ', address_parts($meeting, true));

    $desc = [];
    if ($type !== '' && $topic !== '') {
        $desc[] = 'Тема: ' . $topic;
    }
    if (!empty($meeting['senior_name'])) {
        $desc[] = 'Старший на встрече: ' . $meeting['senior_name'];
    }
    if (!empty($meeting['stream_url'])) {
        $desc[] = 'Трансляция: ' . $meeting['stream_url'];
    }

    $intent = 'intent://#Intent;action=android.intent.action.INSERT;type=vnd.android.cursor.dir/event'
        . ';S.title=' . rawurlencode($title)
        . ';l.beginTime=' . $begin
        . ';l.endTime=' . $end
        . ';S.eventLocation=' . rawurlencode($location)
        . ';S.description=' . rawurlencode(implode("\n", $desc));

    $fallback = google_calendar_link($meeting);
    if ($fallback !== '') {
        $intent .= ';S.browser_fallback_url=' . rawurlencode($fallback);
    }
    return $intent . ';end';
}

/** Клиент — Android? (определение по User-Agent). */
function is_android_client(): bool
{
    return (bool)preg_match('/Android/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/** Клиент — iOS (iPhone/iPad/iPod)? */
function is_ios_client(): bool
{
    return (bool)preg_match('/iPhone|iPad|iPod/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * URL для встраиваемой мини-карты Яндекс.Карт (без ключа API).
 * Строится только из geo_link места: по координатам (ll) или по параметру text.
 * Если geo_link не заполнен или из него нельзя собрать карту — пустая строка.
 */
function yandex_map_widget(array $place): string
{
    $geo = trim((string)($place['geo_link'] ?? ''));
    if ($geo === '') {
        return '';
    }
    $geo = str_ireplace('%2C', ',', $geo);
    if (preg_match('/[?&]ll=([0-9.]+),([0-9.]+)/i', $geo, $m)) {
        $lon = $m[1];
        $lat = $m[2];
        $z = '';
        if (preg_match('/[?&]z=([0-9]{1,2})/i', $geo, $zm)) {
            $z = '&z=' . $zm[1];
        }
        return 'https://yandex.ru/map-widget/v1/?ll=' . urlencode($lon . ',' . $lat) . $z
            . '&pt=' . urlencode($lon . ',' . $lat) . ',pm2rdl';
    }
    if (preg_match('/[?&]text=([^&]+)/i', $geo, $m)) {
        return 'https://yandex.ru/map-widget/v1/?text=' . urlencode(urldecode($m[1]));
    }
    return '';
}

/**
 * Если geo_link — короткая ссылка Яндекс.Карт (https://yandex.ru/maps/-/КОД),
 * пытается получить конечный URL после редиректов и подменить ссылку полным видом
 * (с координатами ll). Если расширить не удалось — возвращает исходную строку.
 */
function expand_yandex_maps_link(string $geo): string
{
    $geo = trim($geo);
    if ($geo === '') {
        return '';
    }
    // уже есть координаты/адрес — из неё карта строится, не трогаем
    if (preg_match('/[?&](?:ll|text|pt)=/i', $geo)) {
        return $geo;
    }
    // расширяем только короткие ссылки вида /maps/-/КОД
    if (!preg_match('~^https?://yandex\.(?:ru|com|ua|by|kz|uz|kg|am|ge|az)/maps/-/~i', $geo)) {
        return $geo;
    }
    $final = http_effective_url($geo);
    if ($final === '' || !preg_match('~^https?://~i', $final)) {
        return $geo;
    }
    // Яндекс кодирует запятую координат как %2C — приводим для разбора
    $parsable = str_ireplace('%2C', ',', $final);
    $lon = '';
    $lat = '';
    $z = '';
    if (preg_match('~[?&]ll=([0-9]+(?:\.[0-9]+)?),([0-9]+(?:\.[0-9]+)?)~i', $parsable, $m)) {
        $lon = $m[1];
        $lat = $m[2];
        if (preg_match('~[?&]z=([0-9]{1,2})~i', $parsable, $zm)) {
            $z = $zm[1];
        }
    } elseif (preg_match('~[?&]pt=([0-9]+(?:\.[0-9]+)?),([0-9]+(?:\.[0-9]+)?)~i', $parsable, $m)) {
        $lon = $m[1];
        $lat = $m[2];
    } elseif (preg_match('~whatshere(?:%5B|\[)point(?:%5D|\])=([0-9]+(?:\.[0-9]+)?),([0-9]+(?:\.[0-9]+)?)~i', $parsable, $m)) {
        $lon = $m[1];
        $lat = $m[2];
    }
    if ($lon !== '' && $lat !== '') {
        return 'https://yandex.ru/maps/?ll=' . urlencode($lon . ',' . $lat)
            . ($z !== '' ? '&z=' . $z : '')
            . '&pt=' . urlencode($lon . ',' . $lat) . ',pm2rdl';
    }
    // координат нет, но ссылка стала полной — вернём как есть
    return $final;
}

/** Конечный URL после цепочки HTTP-редиректов (или исходный при ошибке). */
function http_effective_url(string $url): string
{
    // cURL надёжно отрабатывает редиректы и отдаёт финальный URL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; cms-geolink/1.0)',
        ]);
        $body = @curl_exec($ch);
        if (curl_errno($ch) === 0 && curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0) {
            $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            if (preg_match('~^https?://~i', $effective)) {
                return $effective;
            }
        } else {
            curl_close($ch);
        }
    }

    // фолбэк: ручной обход редиректов через file_get_contents
    $cur = $url;
    for ($i = 0; $i < 8; $i++) {
        $ctx = stream_context_create(['http' => [
            'timeout' => 3,
            'follow_location' => 0,
            'header' => "User-Agent: Mozilla/5.0 (compatible; cms-geolink/1.0)\r\n",
        ]]);
        @file_get_contents($cur, false, $ctx);
        $loc = '';
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('~^Location:\s*(.+)$~i', $h, $m)) {
                $loc = trim($m[1]);
            }
        }
        if ($loc === '') {
            return $cur;
        }
        // приводим относительный Location к абсолютному
        if (!preg_match('~^https?://~i', $loc)) {
            $pu = parse_url($cur);
            $scheme = $pu['scheme'] ?? 'https';
            $host = $pu['host'] ?? '';
            if ($host === '') {
                return $cur;
            }
            $loc = $scheme . '://' . $host . ($loc[0] === '/' ? '' : '/') . $loc;
        }
        $cur = $loc;
    }
    return $cur;
}

function telegram_nick(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    $v = ltrim($v, '@');
    if (preg_match('~t\.me/([A-Za-z0-9_]+)~', $v, $m)) {
        $v = $m[1];
    }
    return $v;
}

function telegram_link(string $value): string
{
    $nick = telegram_nick($value);
    return $nick === '' ? '' : 'https://t.me/' . urlencode($nick);
}

function mail_from(): string
{
    return (string)Setting::get('mail_from', 'noreply@example.com');
}

function send_email(string $to, string $subject, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from = mail_from();
    $headers = 'From: ' . $from . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n";
    return (bool)@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
}