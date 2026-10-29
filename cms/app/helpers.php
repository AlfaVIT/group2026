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
    'view_stats'       => ['Старший', 'Помощник'],
];

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        $refHost = strtolower((string)(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) ?? ''));
        $selfHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
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

/** Записывает посещение пользователя и хранит только последние 50 записей. */
function log_visit(int $userId): void
{
    $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim(explode(',', $ip)[0]);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    Db::insert('visits', [
        'user_id' => $userId,
        'ip' => $ip,
        'user_agent' => $ua,
    ]);
    Db::q('DELETE FROM visits WHERE id NOT IN (SELECT id FROM visits ORDER BY id DESC LIMIT 50)');
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
 * Число очных участников встречи из числа активных участников: те, кто явно
 * отметил своё очное участие (status = 'present'). Гости прибавляются отдельно.
 */
function present_active_count(int $meetingId): int
{
    $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
    return (int)(Db::value(
        "SELECT COUNT(*) FROM users
         WHERE is_active = 1 AND role IN ($ph)
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
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
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
            'timeout' => 6,
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