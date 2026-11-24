<?php

/**
 * Шифрование чувствительных полей «на лету» (AES-256-GCM, openssl).
 *
 * Формат шифртекста: e1:<base64(nonce[12] || tag[16] || ciphertext)>.
 * Пустая строка остаётся пустой: проверки вида `phone != ''` в коде
 * должны продолжать работать без изменений.
 *
 * Ключ — 32 случайных байта (base64) в файле data/keys/master.key.
 * Каталог закрыт .htaccess и правами 0700/0600, в git не попадает.
 *
 * ВАЖНО: потеря ключа = потеря данных. Копию ключа хранить отдельно
 * от копии базы, иначе шифрование бессмысленно.
 *
 * Для поиска по зашифрованному полю (email, логин) используется «слепой
 * индекс»: HMAC-SHA256 от нормализованного значения на отдельном подключе.
 * Индекс детерминирован, поэтому годится только для точного совпадения.
 */
class Crypto
{
    public const PREFIX = 'e1:';

    private const CIPHER    = 'aes-256-gcm';
    private const NONCE_LEN = 12;
    private const TAG_LEN   = 16;
    private const KEY_LEN   = 32;

    private static ?string $key = null;
    private static ?string $indexKey = null;

    /** Шифрование включено: есть расширение, ключ и файл-переключатель. */
    public static function enabled(): bool
    {
        if (!self::available() || !is_file(self::keyPath())) {
            return false;
        }
        if (defined('CRYPTO_ENABLED') && CRYPTO_ENABLED) {
            return true;
        }
        // Рабочий переключатель — файл рядом с ключом, а не константа в коде:
        // включение не меняет git-версию и легко откатывается.
        return is_file(self::flagPath());
    }

    /** Файл-переключатель шифрования (data/keys/enabled). */
    public static function flagPath(): string
    {
        return dirname(self::keyPath()) . '/enabled';
    }

    public static function available(): bool
    {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }
        return in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    public static function keyPath(): string
    {
        if (defined('CRYPTO_KEY_FILE')) {
            return CRYPTO_KEY_FILE;
        }
        return BASE_PATH . '/data/keys/master.key';
    }

    /** Создать каталог и файл ключа (вызывается из установщика/диагностики). */
    public static function createKey(): string
    {
        $path = self::keyPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new CryptoException('Не удалось создать каталог для ключа: ' . $dir);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
            @chmod($dir . '/.htaccess', 0600);
        }
        $key = base64_encode(random_bytes(self::KEY_LEN));
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $key, LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new CryptoException('Не удалось записать ключ: ' . $path);
        }
        @chmod($path, 0600);
        self::$key = null;
        self::$indexKey = null;
        return $key;
    }

    /** Сбросить закэшированные ключи (после смены файла ключа). */
    public static function resetKeys(): void
    {
        self::$key = null;
        self::$indexKey = null;
    }

    /** Проверить, что файл ключа на месте и корректен. */
    public static function keyIsValid(): bool
    {
        try {
            return strlen(self::key()) === self::KEY_LEN;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $key = self::key();
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ct = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LEN);
        if ($ct === false || strlen($tag) !== self::TAG_LEN) {
            throw new CryptoException('Не удалось зашифровать значение');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ct);
    }

    public static function isEncrypted($value): bool
    {
        return is_string($value) && strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /** Расшифровать; значение без префикса возвращается как есть (старые данные). */
    public static function decrypt(string $value): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::NONCE_LEN + self::TAG_LEN) {
            throw new CryptoException('Повреждённый шифртекст');
        }
        $nonce = substr($raw, 0, self::NONCE_LEN);
        $tag = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ct = substr($raw, self::NONCE_LEN + self::TAG_LEN);
        $plain = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) {
            throw new CryptoException('Не удалось расшифровать значение (не тот ключ или данные повреждены)');
        }
        return $plain;
    }

    /** Как decrypt(), но не падает: при ошибке возвращает исходное значение. */
    public static function tryDecrypt(string $value, string $scope = ''): string
    {
        if (!self::isEncrypted($value)) {
            return $value;
        }
        try {
            return self::decrypt($value);
        } catch (Throwable $e) {
            // Не подменяем данные пустой строкой: лучше показать «e1:...»,
            // чем молча потерять значение (например, при неверном ключе).
            error_log('Crypto: ' . ($scope !== '' ? $scope . ': ' : '') . $e->getMessage());
            return $value;
        }
    }

    /**
     * Слепой индекс для точного поиска. $value должен быть уже нормализован
     * (нижний регистр, trim, для телефона — только цифры).
     */
    public static function blindIndex(string $scope, string $value): string
    {
        if ($value === '') {
            return '';
        }
        $mac = hash_hmac('sha256', $scope . "\0" . $value, self::indexKey(), true);
        return substr(bin2hex($mac), 0, 32);
    }

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $path = self::keyPath();
        if (!is_file($path)) {
            throw new CryptoException('Нет файла ключа: ' . $path);
        }
        $raw = base64_decode(trim((string)file_get_contents($path)), true);
        if ($raw === false || strlen($raw) !== self::KEY_LEN) {
            throw new CryptoException('Некорректный файл ключа: ' . $path);
        }
        return self::$key = $raw;
    }

    /** Подключ для слепых индексов: отдельный от ключа шифрования. */
    private static function indexKey(): string
    {
        if (self::$indexKey !== null) {
            return self::$indexKey;
        }
        return self::$indexKey = hash_hkdf('sha256', self::key(), self::KEY_LEN, 'cms-blind-index-v1');
    }
}

class CryptoException extends RuntimeException
{
}

/** Состояние шифрования для вкладки «Обслуживание». */
function crypto_status(): array
{
    $keyExists = is_file(Crypto::keyPath());
    return [
        'available'     => Crypto::available(),
        'enabled'       => Crypto::enabled(),
        'flag'          => is_file(Crypto::flagPath()),
        'key_exists'    => $keyExists,
        'key_valid'     => $keyExists && Crypto::keyIsValid(),
        'key_path'      => Crypto::keyPath(),
        'open'          => crypto_count_open(),
        'has_encrypted' => crypto_has_encrypted_data(),
        'sweep_done'    => (string)Setting::get('crypto_sweep_done', '0') === '1',
    ];
}

/** Есть ли в базе уже зашифрованные значения (ключ терять нельзя). */
function crypto_has_encrypted_data(): bool
{
    try {
        foreach (Db::encryptedFields() as $table => $columns) {
            $column = $columns[0];
            if (Db::value("SELECT 1 FROM $table WHERE substr($column, 1, 3) = 'e1:' LIMIT 1") !== null) {
                return true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

/** Сколько ещё открытых (не зашифрованных) значений в шифруемых полях. */
function crypto_count_open(): int
{
    $total = 0;
    foreach (Db::encryptedFields() as $table => $columns) {
        foreach ($columns as $column) {
            $total += (int)Db::value(
                "SELECT COUNT(*) FROM $table
                 WHERE COALESCE($column, '') <> '' AND substr($column, 1, 3) <> 'e1:'"
            );
        }
    }
    return $total;
}

/**
 * Зашифровать оставшиеся открытые значения. Работает порциями по времени
 * и продолжает с того места, где остановилась: можно нажимать кнопку
 * несколько раз, ничего не потеряется и не зашифруется дважды.
 */
function crypto_sweep(int $seconds = 5): array
{
    if (!Crypto::enabled()) {
        return ['ok' => false, 'done' => false, 'encrypted' => 0, 'error' => 'Шифрование выключено (CRYPTO_ENABLED)'];
    }
    $map = Db::encryptedFields();
    $tables = array_keys($map);
    $pos = (string)Setting::get('crypto_sweep_pos', '');
    $curTable = '';
    $curId = 0;
    if ($pos !== '') {
        $parts = explode('|', $pos, 2);
        $curTable = $parts[0];
        $curId = (int)($parts[1] ?? 0);
    }
    $start = array_search($curTable, $tables, true);
    if ($start === false) {
        $start = 0;
        $curId = 0;
    }

    $deadline = hrtime(true) + $seconds * 1_000_000_000;
    $encrypted = 0;

    for ($i = $start; $i < count($tables); $i++) {
        $table = $tables[$i];
        $id = $i === $start ? $curId : 0;
        while (true) {
            $rows = Db::fetchAllRaw("SELECT * FROM $table WHERE id > ? ORDER BY id LIMIT 200", [$id]);
            if (!$rows) {
                break;
            }
            foreach ($rows as $row) {
                $upd = [];
                foreach ($map[$table] as $column) {
                    $value = $row[$column] ?? null;
                    if (is_string($value) && $value !== '' && !Crypto::isEncrypted($value)) {
                        $upd[$column] = $value;
                    }
                }
                if ($upd) {
                    Db::update($table, $upd, ['id' => (int)$row['id']]);
                    $encrypted++;
                }
                $id = (int)$row['id'];
            }
            Setting::set('crypto_sweep_pos', $table . '|' . $id);
            if (hrtime(true) > $deadline) {
                return ['ok' => true, 'done' => false, 'encrypted' => $encrypted];
            }
        }
    }
    Setting::set('crypto_sweep_pos', '');
    Setting::set('crypto_sweep_done', '1');
    // В свободных страницах SQLite остаются прежние открытые значения:
    // переписываем файл целиком, чтобы после перешифровки их там не было.
    try {
        Db::pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        Db::pdo()->exec('VACUUM');
    } catch (Throwable $e) {
        error_log('Crypto: VACUUM после перешифровки не выполнен: ' . $e->getMessage());
    }
    return ['ok' => true, 'done' => true, 'encrypted' => $encrypted];
}
