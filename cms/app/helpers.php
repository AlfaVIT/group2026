<?php

const ROLES = ['Старший', 'Помощник', 'Казначей', 'СестраБрат', 'Гость'];
const MEETING_ROLES = ['СтаршийВстречи', 'СтаршийПоМолитве', 'ПомощникПоОрганизации', 'ПомощникПоПитанию'];
const ATTENDANCE_STATUSES = [
    'present' => 'Буду присутствовать',
    'online'  => 'Буду онлайн',
    'absent'  => 'Буду отсутствовать',
];
const TREASURY_PURPOSES = ['РегулярныйВзнос', 'Десятина', 'Прочее'];
const ADDRESS_TERMS = ['брат', 'сестра'];

const PERMISSIONS = [
    'manage_users'     => ['Старший'],
    'manage_places'    => ['Старший', 'Помощник'],
    'manage_meetings'  => ['Старший', 'Помощник'],
    'manage_settings'  => ['Старший'],
    'mark_attendance'  => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
    'view_attendance'  => ['Старший', 'Помощник'],
    'assign_roles'     => ['Старший', 'Помощник'],
    'add_expense'      => ['Старший', 'Помощник', 'Казначей', 'СестраБрат'],
    'view_expenses'    => ['Старший', 'Казначей'],
    'view_treasury'    => ['Казначей'],
];

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function redirect(string $url): void
{
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

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Ошибка безопасности: истекла или неверна защитная метка формы.');
    }
}

function money($amount): string
{
    return number_format((float)$amount, 0, ',', ' ') . ' ₽';
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

function full_name(array $user): string
{
    return $user['address_term'] . ' ' . $user['name'];
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

function attendance_badge_class(string $status): string
{
    return [
        'present' => 'text-bg-success',
        'online'  => 'text-bg-info',
        'absent'  => 'text-bg-danger',
    ][$status] ?? 'text-bg-secondary';
}

function short_role(string $role): string
{
    return [
        'СтаршийВстречи'          => 'Ст. встречи',
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

function address_parts(array $place): array
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
    $joined = implode(', ', $parts);
    if ($extra) {
        $joined .= $joined ? ' (' . implode(', ', $extra) . ')' : implode(', ', $extra);
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