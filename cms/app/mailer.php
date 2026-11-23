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

/** Базовый адрес сайта для ссылок в письмах (в cron HTTP_HOST нет — вернёт ''). */
function email_site_url(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^[a-z0-9.\-:]+$/i', $host)) {
        return '';
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strpos(strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')), 'https') === 0;
    return ($https ? 'https://' : 'http://') . $host . base_url() . '/';
}

/** PNG-рыбка для писем (если файла нет — письмо уйдёт без логотипа). */
function email_logo_path(): string
{
    $path = BASE_PATH . '/assets/img/mail-fish.png';
    return is_file($path) ? $path : '';
}

/**
 * multipart/related: HTML-часть + встроенная картинка с Content-ID.
 * Почтовые клиенты не показывают SVG и режут data:, поэтому логотип
 * вкладывается отдельной частью и подставляется через cid:.
 */
function email_related_body(string $html, string $logoPath, string $boundary): string
{
    $data = (string)@file_get_contents($logoPath);
    return '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: image/png; name="fish.png"' . "\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-ID: <cms-fish>\r\n"
        . 'Content-Disposition: inline; filename="fish.png"' . "\r\n\r\n"
        . chunk_split(base64_encode($data), 76, "\r\n")
        . '--' . $boundary . '--';
}

/**
 * HTML-письмо в оформлении сайта: палитра (navy/gold/cream), Georgia в
 * заголовках, табличная вёрстка (её понимают почтовые клиенты), инлайн-стили.
 *
 * $contentHtml — уже готовый HTML (не экранируется).
 * $logoSrc — 'cid:...' для встроенной рыбки или '' без логотипа.
 */
function email_html(string $title, string $contentHtml, string $logoSrc = ''): string
{
    $siteName = Setting::siteName();
    $site = e($siteName);

    // в теме письма часто есть «— Название сайта»; в заголовке карточки это лишнее
    $cleanTitle = trim($title);
    $suffix = ' — ' . $siteName;
    if ($suffix !== ' — ' && mb_substr($cleanTitle, -mb_strlen($suffix)) === $suffix) {
        $cleanTitle = rtrim(mb_substr($cleanTitle, 0, mb_strlen($cleanTitle) - mb_strlen($suffix)));
    }
    $titleHtml = e($cleanTitle !== '' ? $cleanTitle : $siteName);

    $siteUrl = email_site_url();
    $footer = $siteUrl !== ''
        ? 'Письмо отправлено автоматически с сайта <a href="' . e($siteUrl) . '" style="color:#1e3a5f;">'
            . e($siteUrl) . '</a>.'
        : 'Письмо отправлено автоматически с сайта «' . $site . '».';
    $date = date('d.m.Y H:i');

    $logoCell = $logoSrc !== ''
        ? '<td width="44" valign="middle" style="padding:0 12px 0 0;">'
            . '<img src="' . e($logoSrc) . '" width="32" height="32" alt="" '
            . 'style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">'
            . '</td>'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{$titleHtml}</title>
<style>
    body { margin: 0; padding: 0; }
    .cms-mail-body a { color: #1e3a5f; text-decoration: underline; }
    @media only screen and (max-width: 600px) {
        .cms-mail-pad { padding: 18px 16px !important; }
        .cms-mail-card-head { padding: 18px 20px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f6f1e6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f1e6;">
<tr>
<td align="center" class="cms-mail-pad" style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="width:100%;max-width:600px;background-color:#fffdf6;border:1px solid #e6dbbd;border-radius:12px;">
        <tr>
            <td style="height:5px;background-color:#b08a2e;font-size:0;line-height:0;">&nbsp;</td>
        </tr>
        <tr>
            <td class="cms-mail-card-head" style="background-color:#1e3a5f;padding:20px 28px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    {$logoCell}
                    <td valign="middle">
                        <div style="font-family:Georgia,'Times New Roman',serif;font-size:20px;line-height:1.3;color:#f6f1e6;">{$site}</div>
                        <div style="font-family:'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.4;color:#c9a54a;margin-top:4px;">
                            Координация деятельности группы катехизации
                        </div>
                    </td>
                </tr></table>
            </td>
        </tr>
        <tr>
            <td style="padding:26px 28px 10px;">
                <h1 style="margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.35;font-weight:normal;color:#152b46;">
                    {$titleHtml}
                </h1>
                <div class="cms-mail-body" style="font-family:'Segoe UI',Arial,sans-serif;font-size:15px;line-height:1.6;color:#33405a;">
                    {$contentHtml}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 28px 26px;">
                <div style="border-top:1px solid #e6dbbd;padding-top:14px;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;line-height:1.5;color:#7b8398;">
                    {$footer}<br>
                    {$date}
                </div>
            </td>
        </tr>
    </table>
    <div style="font-family:'Segoe UI',Arial,sans-serif;font-size:11px;line-height:1.4;color:#9aa1b0;padding:12px 8px 0;">
        Пожалуйста, не отвечайте на это письмо.
    </div>
</td>
</tr>
</table>
</body>
</html>
HTML;
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
    // SMTP требует CRLF в DATA, а HTML-шаблон собирается с LF
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);
    $normalized = str_replace("\n", "\r\n", $normalized);
    $safeBody = preg_replace('/^\./m', '..', $normalized) ?? $normalized;
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
