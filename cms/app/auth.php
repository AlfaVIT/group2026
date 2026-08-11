<?php

class Auth
{
    private static ?array $user = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['uid'])) {
            return null;
        }
        self::$user = Db::fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['uid']]) ?: null;
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Db::fetch('SELECT * FROM users WHERE lower(email) = lower(?)', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (!$user['is_active']) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        self::$user = null;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $roles = PERMISSIONS[$permission] ?? [];
        return in_array($u['role'], $roles, true);
    }
}