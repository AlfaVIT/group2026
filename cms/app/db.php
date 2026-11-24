<?php

class Db
{
    /**
     * Поля, которые шифруются «на лету» (см. Crypto).
     *
     * Шифрование/расшифровка спрятаны здесь, чтобы контроллеры и шаблоны
     * работали с обычными строками и ничего не знали про e1:...
     * Пустые значения не шифруются — проверки вида `phone != ''` сохраняются.
     *
     * Не шифруем: password_hash, login (публичный идентификатор), роли, флаги,
     * даты, суммы (по ним считаются итоги в SQL), ключи статей казны.
     */
    private const ENCRYPTED = [
        'users' => [
            'name', 'phone', 'email', 'birthday',
            'telegram', 'telegram_username', 'telegram_chat_id', 'telegram_code',
        ],
        'places' => [
            'region', 'district', 'locality', 'street', 'house', 'house_letter',
            'entrance', 'floor', 'apartment', 'intercom', 'geo_link', 'note',
        ],
        'visits' => ['ip', 'user_agent'],
        'meeting_notes' => ['body'],
        'meeting_checklist' => ['comment'],
        'treasury_entries' => ['note', 'from_text'],
        'treasury_expenses' => ['description', 'to_text'],
        'expenses' => ['description'],
    ];

    private static ?PDO $pdo = null;
    private static ?bool $emailIdxColumn = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        }
        return self::$pdo;
    }

    /** Таблица => список шифруемых полей (для обслуживания и перешифровки). */
    public static function encryptedFields(): array
    {
        return self::ENCRYPTED;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : self::decode($row);
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return array_map([self::class, 'decode'], self::q($sql, $params)->fetchAll());
    }

    public static function value(string $sql, array $params = [])
    {
        $v = self::q($sql, $params)->fetchColumn();
        if ($v === false) {
            return null;
        }
        return is_string($v) ? Crypto::tryDecrypt($v, 'value') : $v;
    }

    /** Строки без расшифровки: нужно для перешифровки и диагностики. */
    public static function fetchAllRaw(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $data = self::encode($table, $data);
        $cols = implode(', ', array_keys($data));
        $ph = implode(', ', array_fill(0, count($data), '?'));
        self::q("INSERT INTO $table ($cols) VALUES ($ph)", array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        $data = self::encode($table, $data);
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $w = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        return self::q("UPDATE $table SET $set WHERE $w", array_merge(array_values($data), array_values($where)))->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $w = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        return self::q("DELETE FROM $table WHERE $w", array_values($where))->rowCount();
    }

    public static function transaction(callable $fn): void
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $fn();
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Зашифровать поля строки перед записью + поддержать слепой индекс email. */
    private static function encode(string $table, array $data): array
    {
        if ($table === 'users' && array_key_exists('email', $data) && self::hasEmailIdx()) {
            $email = mb_strtolower(trim((string)($data['email'] ?? '')));
            $data['email_idx'] = $email === '' ? '' : Crypto::blindIndex('users.email', $email);
        }
        $fields = self::ENCRYPTED[$table] ?? [];
        if (!$fields || !Crypto::enabled()) {
            return $data;
        }
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            $value = (string)$data[$field];
            if ($value === '' || Crypto::isEncrypted($value)) {
                continue;
            }
            $data[$field] = Crypto::encrypt($value);
        }
        return $data;
    }

    /** Есть ли колонка слепого индекса (миграция v30 могла не выполниться). */
    private static function hasEmailIdx(): bool
    {
        if (self::$emailIdxColumn === null) {
            try {
                $cols = array_column(self::q('PRAGMA table_info(users)')->fetchAll(), 'name');
                self::$emailIdxColumn = in_array('email_idx', $cols, true);
            } catch (Throwable $e) {
                self::$emailIdxColumn = false;
            }
        }
        return self::$emailIdxColumn;
    }

    /** Расшифровать всё, что помечено префиксом (обычный текст проходит насквозь). */
    private static function decode(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && $value !== '' && $value[0] === 'e' && Crypto::isEncrypted($value)) {
                $row[$key] = Crypto::tryDecrypt($value, (string)$key);
            }
        }
        return $row;
    }
}
