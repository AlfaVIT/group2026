<?php

class Setting
{
    private static array $cache = [];

    /**
     * Настройки, которые нельзя хранить открытым текстом: пароль SMTP, токен бота,
     * секрет вебхука, токен планировщика и почта администратора.
     * Шифруются при записи, расшифровываются на чтении (Db::value).
     */
    private const SECRET_KEYS = [
        'smtp_pass',
        'smtp_user',
        'cron_token',
        'telegram_bot_token',
        'telegram_webhook_secret',
        'admin_email',
    ];

    /** @return string[] */
    public static function secretKeys(): array
    {
        return self::SECRET_KEYS;
    }

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    public static function get(string $key, $default = null)
    {
        if (!array_key_exists($key, self::$cache)) {
            $v = Db::value('SELECT value FROM settings WHERE key = ?', [$key]);
            self::$cache[$key] = $v === null ? null : $v;
        }
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        // секреты пишем зашифрованными; пустые значения остаются пустыми
        if (self::isSecret($key) && $value !== '' && !Crypto::isEncrypted($value) && Crypto::enabled()) {
            $value = Crypto::encrypt($value);
        }
        Db::q(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
        // в кеше держим открытое значение — его и ждут вызывающие
        self::$cache[$key] = Crypto::tryDecrypt($value, 'setting:' . $key);
    }

    public static function siteName(): string
    {
        return (string)self::get('site_name', 'Группа катехизации');
    }

    public static function theme(): string
    {
        $theme = (string)self::get('theme', DEFAULT_THEME);
        return in_array($theme, THEMES, true) ? $theme : DEFAULT_THEME;
    }

    /** Общая ссылка на трансляцию: используется во встречах без своей ссылки. */
    public static function streamUrl(): string
    {
        return trim((string)self::get('stream_url', ''));
    }

    /**
     * Почта администратора: на неё отправляются системные уведомления и
     * резервные копии. Пустая строка — адрес ещё не задан.
     */
    public static function adminEmail(): string
    {
        return trim((string)self::get('admin_email', ''));
    }

    /**
     * Токен для запуска обслуживания по URL (cron хостинга или внешний
     * планировщик). Создаётся при первом обращении и хранится в настройках.
     */
    public static function cronToken(): string
    {
        $token = (string)self::get('cron_token', '');
        if (!preg_match('/^[a-f0-9]{32,}$/', $token)) {
            $token = bin2hex(random_bytes(24));
            self::set('cron_token', $token);
        }
        return $token;
    }

    /**
     * Финансовый раздел сайта (страница «Казна», статьи и расходы по встречам).
     * Пока настройка не задана, раздел считается включённым — как было до её появления.
     */
    public static function financeEnabled(): bool
    {
        return (string)self::get('finance_enabled', '1') !== '0';
    }
}