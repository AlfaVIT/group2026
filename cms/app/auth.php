<?php

class Auth
{
    private const REMEMBER_COOKIE = 'cms_remember';
    private const REMEMBER_DAYS = 30;

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

    public static function attempt(string $identifier, string $password): bool
    {
        // SQLite lower() не понимает кириллицу, поэтому нормализуем в PHP:
        // иначе «Иван» не найдёт сохранённый логин «иван»
        $identifier = mb_strtolower(trim($identifier));
        $user = Db::fetch(
            'SELECT * FROM users WHERE lower(email) = ? OR (login != \'\' AND lower(login) = ?)',
            [$identifier, $identifier]
        );
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
        self::clearRemember(self::id());
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    /** Параметры cookie «запомнить меня» (те же, что у сессионной cookie). */
    private static function rememberCookieOptions(int $expires): array
    {
        $p = session_get_cookie_params();
        return [
            'expires' => $expires,
            'path' => $p['path'] !== '' ? $p['path'] : '/',
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => true,
            'samesite' => $p['samesite'] ?? 'Lax',
        ];
    }

    /**
     * Выдаёт долгоживущий токен «запомнить меня». Хранится в БД (только хеш),
     * потому что срок жизни серверной сессии обычно 24 минуты — cookie сессии
     * этого не решает.
     */
    public static function issueRemember(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        Db::update('users', [
            'remember_token'   => hash('sha256', $token),
            'remember_expires' => date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * 86400),
        ], ['id' => $userId]);
        setcookie(self::REMEMBER_COOKIE, $userId . ':' . $token, self::rememberCookieOptions(time() + self::REMEMBER_DAYS * 86400));
    }

    /** Сбрасывает токен «запомнить меня»: в БД и в браузере. */
    public static function clearRemember(?int $userId = null): void
    {
        if ($userId !== null && $userId > 0) {
            Db::update('users', ['remember_token' => '', 'remember_expires' => null], ['id' => $userId]);
        }
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, '', self::rememberCookieOptions(time() - 3600));
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    /** Восстанавливает сессию по токену «запомнить меня», если сессия потеряна. */
    public static function resumeFromRemember(): void
    {
        if (!empty($_SESSION['uid']) || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        if (!preg_match('/^(\d+):([a-f0-9]{64})$/', (string)$_COOKIE[self::REMEMBER_COOKIE], $m)) {
            self::clearRemember();
            return;
        }
        $userId = (int)$m[1];
        $token = $m[2];
        try {
            $row = Db::fetch(
                'SELECT id, remember_token, remember_expires FROM users WHERE id = ? AND is_active = 1',
                [$userId]
            );
        } catch (Throwable $e) {
            // схема ещё не обновлена — не ломаем страницу, просто не восстанавливаем сессию
            return;
        }
        $expires = (string)($row['remember_expires'] ?? '');
        if (!$row || (string)$row['remember_token'] === ''
            || $expires === '' || $expires < date('Y-m-d H:i:s')
            || !hash_equals((string)$row['remember_token'], hash('sha256', $token))) {
            self::clearRemember($row ? $userId : null);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        self::$user = null;
        // скользящее окно: продлеваем токен при каждом восстановлении
        self::issueRemember($userId);
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