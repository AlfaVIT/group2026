<?php

function install_schema(): void
{
    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0777, true);
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $schema = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    address_term TEXT NOT NULL DEFAULT 'брат' CHECK (address_term IN ('брат','сестра')),
    name TEXT NOT NULL,
    phone TEXT DEFAULT '',
    birthday TEXT DEFAULT '',
    email TEXT NOT NULL UNIQUE,
    telegram TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'СестраБрат',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS places (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    region TEXT DEFAULT '',
    district TEXT DEFAULT '',
    locality TEXT DEFAULT '',
    street TEXT DEFAULT '',
    house TEXT DEFAULT '',
    house_letter TEXT DEFAULT '',
    entrance TEXT DEFAULT '',
    floor TEXT DEFAULT '',
    apartment TEXT DEFAULT '',
    intercom TEXT DEFAULT '',
    geo_link TEXT DEFAULT '',
    note TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS place_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    place_id INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS meetings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    place_id INTEGER REFERENCES places(id) ON DELETE SET NULL,
    topic TEXT DEFAULT '',
    materials TEXT DEFAULT '[]',
    guests TEXT DEFAULT '',
    participants_count INTEGER DEFAULT 0,
    stream_url TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status TEXT NOT NULL CHECK (status IN ('present','online','absent')),
    reason TEXT DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (meeting_id, user_id)
);

CREATE TABLE IF NOT EXISTS meeting_roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    UNIQUE (meeting_id, user_id, role)
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount REAL NOT NULL,
    description TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS treasury_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    from_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    from_text TEXT DEFAULT '',
    purpose TEXT NOT NULL,
    amount REAL NOT NULL,
    note TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;

    $pdo->exec($schema);

    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('site_name', 'Группа катехизации')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('theme', '" . DEFAULT_THEME . "')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('mail_from', 'noreply@example.com')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '2')");
}

function run_migrations(): void
{
    if (!is_file(DB_PATH)) {
        return;
    }
    try {
        $version = (int)(Db::value('SELECT value FROM settings WHERE key = ?', ['schema_version']) ?? 0);
        if ($version >= 2) {
            return;
        }

        $cols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('telegram', $cols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN telegram TEXT DEFAULT ''");
        }
        Db::q('CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('mail_from', 'noreply@example.com')");
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '2')");
        Db::q("UPDATE settings SET value = '2' WHERE key = 'schema_version'");
    } catch (Throwable $e) {
    }
}

function install_create_admin(array $data): array
{
    $errors = [];
    if (!in_array($data['address_term'], ADDRESS_TERMS, true)) {
        $errors[] = 'Выберите обращение (брат или сестра)';
    }
    if (mb_strlen($data['name']) < 2) {
        $errors[] = 'Укажите имя (не менее 2 символов)';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный email';
    }
    if (mb_strlen($data['password']) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов';
    }
    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = 'Пароли не совпадают';
    }
    if ($errors) {
        return $errors;
    }

    Db::insert('users', [
        'address_term'  => $data['address_term'],
        'name'          => $data['name'],
        'phone'         => $data['phone'] ?? '',
        'birthday'      => $data['birthday'] ?? '',
        'email'         => mb_strtolower(trim($data['email'])),
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role'          => 'Старший',
        'is_active'     => 1,
    ]);
    return [];
}