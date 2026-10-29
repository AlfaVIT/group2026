<?php

class Telegram
{
    /** Тестовый хук: если задан, api() вызывает его вместо HTTP-запроса. */
    public static $mock = null;

    public static function token(): string
    {
        return (string)Setting::get('telegram_bot_token', '');
    }

    public static function botUsername(): string
    {
        return ltrim((string)Setting::get('telegram_bot_username', ''), '@');
    }

    public static function configured(): bool
    {
        return self::token() !== '';
    }

    public static function api(string $method, array $params = []): ?array
    {
        if (self::$mock !== null) {
            return call_user_func(self::$mock, $method, $params);
        }
        $token = self::token();
        if ($token === '') {
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded' . "\r\n",
                'content' => http_build_query($params),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents('https://api.telegram.org/bot' . $token . '/' . $method, false, $ctx);
        if ($body === false) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function sendMessage($chatId, string $text, array $extra = []): ?array
    {
        return self::api('sendMessage', ['chat_id' => $chatId, 'text' => $text] + $extra);
    }

    public static function setWebhook(string $url): ?array
    {
        return self::api('setWebhook', ['url' => $url]);
    }

    public static function deleteWebhook(): ?array
    {
        return self::api('deleteWebhook');
    }

    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /** Разбивает длинное сообщение на части не длиннее 4096 символов, по строкам. */
    public static function split(string $text, int $limit = 4096): array
    {
        $text = trim($text);
        if ($text === '' || $limit <= 0) {
            return [];
        }
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }
        $chunks = [];
        $rest = $text;
        while (mb_strlen($rest) > $limit) {
            $part = mb_substr($rest, 0, $limit);
            $cut = mb_strrpos($part, "\n");
            if ($cut === false || $cut < (int)($limit / 2)) {
                $cut = $limit;
            }
            $chunks[] = mb_substr($part, 0, $cut);
            $rest = mb_substr($rest, $cut);
        }
        $chunks[] = $rest;
        return $chunks;
    }
}

/** Код активации для пользователя: генерируется один раз и хранится в users.telegram_code. */
function telegram_activation_code(int $userId): string
{
    $row = Db::fetch('SELECT telegram_code FROM users WHERE id = ?', [$userId]);
    if (!$row) {
        return '';
    }
    $code = (string)($row['telegram_code'] ?? '');
    if ($code === '') {
        $code = bin2hex(random_bytes(8));
        Db::update('users', ['telegram_code' => $code], ['id' => $userId]);
    }
    return $code;
}

/** Ссылка «Подключить бота» вида https://t.me/<bot>?start=tg<userId>_<code>. */
function telegram_activation_link(int $userId): string
{
    if (!Telegram::configured()) {
        return '';
    }
    $username = Telegram::botUsername();
    if ($username === '') {
        return '';
    }
    $code = telegram_activation_code($userId);
    if ($code === '') {
        return '';
    }
    return 'https://t.me/' . $username . '?start=tg' . $userId . '_' . $code;
}

/**
 * Обрабатывает обновление от Telegram (webhook). Возвращает список ответов
 * вида ['chat_id' => int, 'text' => string] — их отправляет точка входа.
 */
function telegram_handle_update(array $update): array
{
    $message = $update['message'] ?? null;
    if (!is_array($message)) {
        return [];
    }
    $chatId = $message['chat']['id'] ?? null;
    if ($chatId === null) {
        return [];
    }
    $text = trim((string)($message['text'] ?? ''));
    $username = trim((string)($message['from']['username'] ?? ''));

    if (preg_match('~^/start(?:\s+(.+))?$~u', $text, $m)) {
        $payload = trim((string)($m[1] ?? ''));
        if (preg_match('~^tg(\d+)_([a-f0-9]{16})$~', $payload, $p)) {
            $userId = (int)$p[1];
            $code = $p[2];
            $user = Db::fetch('SELECT id, name, address_term, telegram_code FROM users WHERE id = ?', [$userId]);
            if ($user && hash_equals((string)$user['telegram_code'], $code)) {
                Db::update('users', [
                    'telegram_chat_id' => (int)$chatId,
                    'telegram_username' => $username,
                ], ['id' => $userId]);
                return [[
                    'chat_id' => (int)$chatId,
                    'text' => full_name($user) . ', вы подключены к боту группы! Теперь сообщения будут приходить сюда.',
                ]];
            }
            return [[
                'chat_id' => (int)$chatId,
                'text' => 'Ссылка недействительна. Откройте её заново из раздела «Бот» в своём профиле на сайте.',
            ]];
        }
        return [[
            'chat_id' => (int)$chatId,
            'text' => 'Чтобы получать сообщения группы, откройте ссылку из раздела «Бот» в своём профиле на сайте.',
        ]];
    }

    if ($text === '/help') {
        return [['chat_id' => (int)$chatId, 'text' => 'Нажмите /start, чтобы подключиться к боту группы.']];
    }

    return [];
}