<?php

/**
 * Отправка почты: через SMTP (если настроен) или встроенным mail().
 *
 * Зачем SMTP: на shared-хостинге mail() уходит с сервера хостинга, и если
 * почта домена обслуживается другим провайдером (например, у opensite.su —
 * Mail.ru, SPF redirect=_spf.mail.ru), письмо не проходит SPF и теряется.
 * SMTP с ящиком на домене решает это: From совпадает с отправителем.
 *
 * Клиент SMTP написан вручную — на хостинге нет Composer и библиотек.
 */

/** Настройки SMTP из БД (пароль хранится в settings, как токен Telegram). */
function mailer_config(): array
{
    return [
        'enabled' => (string)Setting::get('smtp_enabled', '0') === '1',
        'host'    => trim((string)Setting::get('smtp_host', '')),
        'port'    => (int)Setting::get('smtp_port', '465'),
        'secure'  => (string)Setting::get('smtp_secure', 'ssl'),
        'user'    => trim((string)Setting::get('smtp_user', '')),
        'pass'    => (string)Setting::get('smtp_pass', ''),
    ];
}

/**
 * Адрес отправителя: при включённом SMTP — логин ящика (иначе провайдер
 * отклонит письмо или испортит SPF), иначе — настройка «Email отправителя».
 */
function mailer_from(): string
{
    $c = mailer_config();
    if ($c['enabled'] && filter_var($c['user'], FILTER_VALIDATE_EMAIL)) {
        return $c['user'];
    }
    return mail_from();
}

/**
 * Отправляет готовое письмо (headers без To/Subject, тело как есть).
 * Возвращает true/false; текст последней ошибки — в настройке mail_last_error.
 */
function mail_transport_send(string $to, string $subject, string $headers, string $body): bool
{
    $c = mailer_config();
    if ($c['enabled']) {
        $res = smtp_send_message($c, $to, $subject, $headers, $body);
        Setting::set('mail_last_error', $res['ok'] ? '' : mb_substr($res['error'], 0, 500));
        return $res['ok'];
    }

    $encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = (bool)@mail($to, $encoded, $body, $headers);
    Setting::set('mail_last_error', $ok ? '' : 'mail() вернул ошибку — нужен SMTP');
    return $ok;
}

/**
 * SMTP-сессия: EHLO → (STARTTLS) → AUTH → MAIL/RCPT → DATA.
 *
 * @return array{ok: bool, error?: string}
 */
function smtp_send_message(array $c, string $to, string $subject, string $headers, string $body): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный адрес получателя'];
    }
    if ($c['host'] === '' || $c['user'] === '' || $c['pass'] === '') {
        return ['ok' => false, 'error' => 'SMTP не настроен: укажите сервер, логин и пароль'];
    }
    $port = $c['port'] > 0 ? $c['port'] : ($c['secure'] === 'ssl' ? 465 : 587);
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 10);
    if (!$fp) {
        return ['ok' => false, 'error' => 'Не удалось подключиться к ' . $c['host'] . ':' . $port . ' — ' . trim($errstr)];
    }
    stream_set_timeout($fp, 10);

    $read = static function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = strlen($data) >= 3 ? (int)substr($data, 0, 3) : 0;
        return [$data, $code];
    };
    $cmd = static function (string $line) use ($fp, $read): array {
        fwrite($fp, $line . "\r\n");
        return $read();
    };
    $fail = static function (string $message) use ($fp): array {
        @fclose($fp);
        return ['ok' => false, 'error' => $message];
    };

    [$resp, $code] = $read();
    if ($code !== 220) {
        return $fail('SMTP приветствие: ' . trim($resp));
    }

    $hostname = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
    [$resp, $code] = $cmd('EHLO ' . $hostname);
    if ($code !== 250) {
        [$resp, $code] = $cmd('HELO ' . $hostname);
        if ($code !== 250) {
            return $fail('EHLO/HELO: ' . trim($resp));
        }
    }

    if ($c['secure'] === 'tls') {
        [$resp, $code] = $cmd('STARTTLS');
        if ($code !== 220) {
            return $fail('STARTTLS: ' . trim($resp));
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('Не удалось включить TLS');
        }
        [$resp, $code] = $cmd('EHLO ' . $hostname);
        if ($code !== 250) {
            return $fail('EHLO после STARTTLS: ' . trim($resp));
        }
    }

    // AUTH LOGIN поддерживают Mail.ru, Яндекс, Jino; AUTH PLAIN — запасной
    [$resp, $code] = $cmd('AUTH LOGIN');
    if ($code === 334) {
        [$resp, $code] = $cmd(base64_encode($c['user']));
        if ($code !== 334) {
            return $fail('SMTP логин: ' . trim($resp));
        }
        [$resp, $code] = $cmd(base64_encode($c['pass']));
        if ($code !== 235) {
            return $fail('SMTP пароль: ' . trim($resp));
        }
    } else {
        [$resp, $code] = $cmd('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $c['pass']));
        if ($code !== 235) {
            return $fail('SMTP авторизация: ' . trim($resp));
        }
    }

    $from = mailer_from();
    [$resp, $code] = $cmd('MAIL FROM:<' . $from . '>');
    if ($code !== 250) {
        return $fail('MAIL FROM ' . $from . ': ' . trim($resp));
    }
    [$resp, $code] = $cmd('RCPT TO:<' . $to . '>');
    if ($code !== 250 && $code !== 251) {
        return $fail('RCPT TO ' . $to . ': ' . trim($resp));
    }
    [$resp, $code] = $cmd('DATA');
    if ($code !== 354) {
        return $fail('DATA: ' . trim($resp));
    }

    if (substr($headers, -2) !== "\r\n") {
        $headers .= "\r\n";
    }
    $safeBody = preg_replace('/^\./m', '..', $body) ?? $body;
    $data = 'To: ' . $to . "\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . $headers . "\r\n"
        . $safeBody . "\r\n.\r\n";
    fwrite($fp, $data);
    [$resp, $code] = $read();
    if ($code !== 250) {
        return $fail('Отправка письма: ' . trim($resp));
    }
    $cmd('QUIT');
    @fclose($fp);
    return ['ok' => true];
}
