<?php

class Setting
{
    private static array $cache = [];

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
        Db::q(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
        self::$cache[$key] = $value;
    }

    public static function siteName(): string
    {
        return (string)self::get('site_name', 'Группа катехизации');
    }

    public static function theme(): string
    {
        $theme = (string)self::get('theme', DEFAULT_THEME);
        return is_dir(BASE_PATH . '/templates/' . $theme) && is_file(BASE_PATH . '/templates/' . $theme . '/layout.php')
            ? $theme : DEFAULT_THEME;
    }
}