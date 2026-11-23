<?php

/**
 * Обслуживание сайта: резервные копии базы и системные уведомления.
 *
 * Бекап запускается по расписанию из настроек (вкладка «Обслуживание»):
 * либо cron-скриптом cms/cron/backup.php, либо по URL с токеном
 * (?p=backup_cron&token=...). Скрипт и URL можно вызывать часто (например,
 * раз в час) — письмо уйдёт только когда наступил запланированный момент.
 */

/* ------------------------------------------------------------------ */
/* Резервные копии                                                    */
/* ------------------------------------------------------------------ */

/** Текущие настройки расписания бекапов. */
function backup_schedule(): array
{
    $freq = (string)Setting::get('backup_freq', 'daily');
    if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
        $freq = 'daily';
    }
    $time = trim((string)Setting::get('backup_time', '03:00'));
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        $time = '03:00';
    }
    $weekday = (int)Setting::get('backup_weekday', '1');
    if ($weekday < 1 || $weekday > 7) {
        $weekday = 1;
    }
    $day = (int)Setting::get('backup_day', '1');
    if ($day < 1 || $day > 28) {
        $day = 1;
    }
    return [
        'enabled'    => (string)Setting::get('backup_enabled', '0') === '1',
        'freq'       => $freq,
        'time'       => $time,
        'weekday'    => $weekday,
        'day'        => $day,
        'last'       => (string)Setting::get('backup_last', ''),
        'last_error' => (string)Setting::get('backup_last_error', ''),
    ];
}

/**
 * Последний запланированный момент не позже $now (или null при ошибке даты).
 * Именно с ним сравнивается backup_last, чтобы не зависеть от частоты вызова cron.
 */
function backup_last_slot(array $s, ?int $now = null): ?int
{
    $now = $now ?? time();
    $slot = strtotime(date('Y-m-d', $now) . ' ' . $s['time']);
    if ($slot === false) {
        return null;
    }
    if ($s['freq'] === 'daily') {
        if ($slot > $now) {
            $slot = strtotime('-1 day', $slot);
        }
        return $slot;
    }
    if ($s['freq'] === 'weekly') {
        $delta = (int)date('N', $now) - (int)$s['weekday']; // 0 — сегодня
        $slot -= $delta * 86400;
        if ($slot > $now) {
            $slot -= 7 * 86400;
        }
        return $slot;
    }
    // monthly: число ограничено 1..28, чтобы не зависеть от длины месяца
    $slot = strtotime(sprintf('%s-%02d %s', date('Y-m', $now), (int)$s['day'], $s['time']));
    if ($slot === false) {
        return null;
    }
    if ($slot > $now) {
        $slot = strtotime('-1 month', $slot);
    }
    return $slot;
}

/** Следующий запланированный момент после $now (для подсказки в настройках). */
function backup_next_slot(array $s, ?int $now = null): ?int
{
    $last = backup_last_slot($s, $now);
    if ($last === null) {
        return null;
    }
    $next = match ($s['freq']) {
        'weekly'  => strtotime('+1 week', $last),
        'monthly' => strtotime('+1 month', $last),
        default   => strtotime('+1 day', $last),
    };
    return $next === false ? null : $next;
}

/** Пора ли отправлять бекап: включено, есть адрес и наступил новый слот. */
function backup_is_due(?array $s = null, ?int $now = null): bool
{
    $s = $s ?? backup_schedule();
    if (!$s['enabled'] || Setting::adminEmail() === '') {
        return false;
    }
    $slot = backup_last_slot($s, $now);
    if ($slot === null) {
        return false;
    }
    $last = $s['last'] !== '' ? strtotime($s['last']) : false;
    return $last === false || $last < $slot;
}

/**
 * Согласованный снимок базы: VACUUM INTO учитывает данные из WAL, поэтому
 * копия не «теряет» последние записи. Если VACUUM недоступен — контрольная
 * точка WAL и обычное копирование файла.
 *
 * @return array{path?: string, error?: string}
 */
function backup_snapshot(): array
{
    $base = @tempnam(sys_get_temp_dir(), 'cmsdb');
    if ($base === false) {
        return ['error' => 'Не удалось создать временный файл'];
    }
    @unlink($base); // VACUUM INTO требует, чтобы файла ещё не было
    $path = $base . '.sqlite';

    try {
        Db::q('VACUUM INTO ' . Db::pdo()->quote($path));
    } catch (Throwable $e) {
        try {
            Db::q('PRAGMA wal_checkpoint(TRUNCATE)');
            if (!@copy(DB_PATH, $path)) {
                @unlink($path);
                return ['error' => 'Не удалось скопировать файл базы'];
            }
        } catch (Throwable $e2) {
            @unlink($path);
            return ['error' => 'Не удалось создать снимок базы'];
        }
    }

    if (!is_file($path) || (int)@filesize($path) === 0) {
        @unlink($path);
        return ['error' => 'Снимок базы получился пустым'];
    }
    return ['path' => $path];
}

/**
 * Создаёт и отправляет бекап на почту администратора.
 * $force = true — отправить сейчас, не глядя на расписание.
 *
 * @return array{ok: bool, status: string, message: string}
 */
function backup_send(bool $force = false, ?array $s = null): array
{
    $s = $s ?? backup_schedule();
    $to = Setting::adminEmail();
    if ($to === '') {
        return ['ok' => false, 'status' => 'error', 'message' => 'Сначала укажите почту администратора в «Настройки» → «Общее»'];
    }
    if (!$force && !backup_is_due($s)) {
        return ['ok' => true, 'status' => 'skipped', 'message' => 'По расписанию отправлять пока нечего'];
    }

    $snap = backup_snapshot();
    if (isset($snap['error'])) {
        Setting::set('backup_last_error', $snap['error'] . ' (' . date('d.m.Y H:i') . ')');
        return ['ok' => false, 'status' => 'error', 'message' => $snap['error']];
    }
    $path = (string)$snap['path'];
    $name = 'cms-' . date('Ymd-Hi') . '.sqlite';
    $size = (int)@filesize($path);

    $text = 'Резервная копия базы сайта «' . Setting::siteName() . '»' . "\n\n"
        . 'Файл: ' . $name . "\n"
        . 'Размер: ' . human_size($size) . "\n"
        . 'Дата: ' . date('d.m.Y H:i') . "\n";

    $ok = send_email_attachment($to, 'Бекап базы — ' . Setting::siteName(), $text, $path, $name, 'application/x-sqlite3');
    @unlink($path);

    if (!$ok) {
        Setting::set('backup_last_error', 'Не удалось отправить письмо (' . date('d.m.Y H:i') . ')');
        return ['ok' => false, 'status' => 'error', 'message' => 'Не удалось отправить письмо с бекапом'];
    }
    Setting::set('backup_last', date('Y-m-d H:i:s'));
    Setting::set('backup_last_error', '');
    return ['ok' => true, 'status' => 'sent', 'message' => 'Бекап отправлен на ' . $to . ' (' . human_size($size) . ')'];
}

/* ------------------------------------------------------------------ */
/* Системные уведомления                                              */
/* ------------------------------------------------------------------ */

function security_notify_enabled(string $key): bool
{
    return (string)Setting::get($key, '1') === '1';
}

/** Не отправляем одно и то же чаще, чем раз в $seconds. */
function security_throttled(string $settingKey, int $seconds): bool
{
    $last = (string)Setting::get($settingKey, '');
    $ts = $last !== '' ? strtotime($last) : false;
    return $ts !== false && (time() - $ts) < $seconds;
}

/** «admin» — типовой логин, на который идут попытки подбора. */
function security_is_admin_identifier(string $identifier): bool
{
    return mb_strtolower(trim($identifier)) === 'admin';
}

function security_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function security_client_ua(): string
{
    return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Письмо администратору; при ошибке просто пишем в лог сервера. */
function security_send_mail(string $subject, array $lines): bool
{
    $to = Setting::adminEmail();
    if ($to === '') {
        return false;
    }
    $html = '';
    foreach ($lines as $line) {
        $html .= '<p>' . e((string)$line) . '</p>';
    }
    try {
        return send_email($to, $subject, $html);
    } catch (Throwable $e) {
        error_log('security notification failed: ' . $e->getMessage());
        return false;
    }
}

/** Запоминает неудачную попытку входа (для подсчёта серии). */
function security_log_failure(string $identifier, string $ip, string $ua): void
{
    try {
        Db::insert('login_attempts', [
            'identifier' => mb_substr(mb_strtolower(trim($identifier)), 0, 190),
            'ip'         => mb_substr($ip, 0, 45),
            'user_agent' => $ua,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // журнал нужен только для подсчёта свежих серий
        Db::q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    } catch (Throwable $e) {
        error_log('login attempt log failed: ' . $e->getMessage());
    }
}

/** Уведомление о попытке входа с логином admin (успешной или нет). */
function security_notify_admin_attempt(string $identifier, bool $success, string $ip, string $ua): void
{
    if (!security_notify_enabled('notify_admin_login') || !security_is_admin_identifier($identifier)) {
        return;
    }
    if (security_throttled('notify_last_admin', 1800)) {
        return;
    }
    Setting::set('notify_last_admin', date('Y-m-d H:i:s'));
    security_send_mail(
        ($success ? 'Вход в аккаунт admin' : 'Неудачная попытка входа в admin') . ' — ' . Setting::siteName(),
        [
            $success ? 'Выполнен вход с логином admin.' : 'Кто-то пытался войти с логином admin и не смог.',
            'Логин: ' . $identifier,
            'IP: ' . ($ip !== '' ? $ip : 'неизвестен'),
            'Браузер: ' . ($ua !== '' ? $ua : 'неизвестен'),
            'Время: ' . date('d.m.Y H:i'),
        ]
    );
}

/** Уведомление о подборе пароля: 5+ неудач за 10 минут по логину или IP. */
function security_notify_bruteforce(string $identifier, string $ip): void
{
    if (!security_notify_enabled('notify_bruteforce') || security_throttled('notify_last_brute', 3600)) {
        return;
    }
    try {
        $since = date('Y-m-d H:i:s', time() - 600);
        $count = (int)Db::value(
            'SELECT COUNT(*) FROM login_attempts WHERE created_at >= ? AND (identifier = ? OR ip = ?)',
            [$since, mb_strtolower(trim($identifier)), $ip]
        );
    } catch (Throwable $e) {
        return;
    }
    if ($count < 5) {
        return;
    }
    Setting::set('notify_last_brute', date('Y-m-d H:i:s'));
    security_send_mail('Возможный подбор пароля — ' . Setting::siteName(), [
        'За последние 10 минут зафиксировано неудачных попыток входа: ' . $count . '.',
        'Последний логин: ' . $identifier,
        'IP: ' . ($ip !== '' ? $ip : 'неизвестен'),
        'Браузер: ' . security_client_ua(),
        'Время: ' . date('d.m.Y H:i'),
        'Если это были не вы, стоит сменить пароли и проверить журнал входов.',
    ]);
}

/** Хук: неудачная попытка входа. Никогда не ломает вход. */
function security_on_login_failure(string $identifier): void
{
    try {
        $ip = security_client_ip();
        $ua = security_client_ua();
        security_log_failure($identifier, $ip, $ua);
        security_notify_admin_attempt($identifier, false, $ip, $ua);
        security_notify_bruteforce($identifier, $ip);
    } catch (Throwable $e) {
        error_log('security_on_login_failure: ' . $e->getMessage());
    }
}

/** Хук: успешный вход (уведомляем только про логин admin). */
function security_on_login_success(string $identifier): void
{
    try {
        security_notify_admin_attempt($identifier, true, security_client_ip(), security_client_ua());
    } catch (Throwable $e) {
        error_log('security_on_login_success: ' . $e->getMessage());
    }
}
