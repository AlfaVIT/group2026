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
    hide_phone INTEGER NOT NULL DEFAULT 0,
    birthday TEXT DEFAULT '',
    login TEXT DEFAULT '',
    email TEXT DEFAULT '',
    telegram TEXT DEFAULT '',
    telegram_chat_id INTEGER DEFAULT NULL,
    telegram_username TEXT DEFAULT '',
    telegram_code TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'СестраБрат',
    is_active INTEGER NOT NULL DEFAULT 1,
    font_size INTEGER NOT NULL DEFAULT 100,
    theme TEXT DEFAULT '',
    avatar TEXT DEFAULT '',
    personal_consent INTEGER NOT NULL DEFAULT 0,
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
    tag TEXT DEFAULT '',
    meeting_type TEXT DEFAULT '',
    materials TEXT DEFAULT '[]',
    guests TEXT DEFAULT '',
    participants_count INTEGER DEFAULT 0,
    senior_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
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
    article TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ip TEXT DEFAULT '',
    user_agent TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS treasury_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    period TEXT DEFAULT '',
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

CREATE TABLE IF NOT EXISTS treasury_expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    period TEXT DEFAULT '',
    amount REAL NOT NULL,
    type TEXT DEFAULT '',
    description TEXT DEFAULT '',
    to_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    to_text TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS materials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    content TEXT DEFAULT '',
    image_path TEXT DEFAULT '',
    tag TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at TEXT
);

CREATE TABLE IF NOT EXISTS meeting_checklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    item TEXT NOT NULL,
    checked INTEGER NOT NULL DEFAULT 0,
    comment TEXT DEFAULT '',
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (meeting_id, item)
);

CREATE TABLE IF NOT EXISTS vespers_readers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    passage_key TEXT NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (meeting_id, passage_key)
);
SQL;

    $pdo->exec($schema);

    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('site_name', 'Группа катехизации')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('theme', '" . DEFAULT_THEME . "')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('mail_from', 'noreply@example.com')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '21')");
}

function run_migrations(): void
{
    if (!is_file(DB_PATH)) {
        return;
    }
    try {
        $version = (int)(Db::value('SELECT value FROM settings WHERE key = ?', ['schema_version']) ?? 0);
        if ($version >= 21) {
            return;
        }

        $cols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('theme', $cols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN theme TEXT DEFAULT ''");
        }
        if (!in_array('telegram', $cols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN telegram TEXT DEFAULT ''");
        }
        if (!in_array('font_size', $cols, true)) {
            Db::q('ALTER TABLE users ADD COLUMN font_size INTEGER NOT NULL DEFAULT 100');
        }
        if (!in_array('login', $cols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN login TEXT DEFAULT ''");
            foreach (Db::fetchAll("SELECT id, email FROM users WHERE email != ''") as $u) {
                $login = explode('@', mb_strtolower($u['email']), 2)[0];
                if ($login !== '') {
                    Db::q('UPDATE users SET login = ? WHERE id = ?', [$login, $u['id']]);
                }
            }
        }
        $mCols = array_column(Db::fetchAll('PRAGMA table_info(meetings)'), 'name');
        if (!in_array('tag', $mCols, true)) {
            Db::q("ALTER TABLE meetings ADD COLUMN tag TEXT DEFAULT ''");
        }
        Db::q('CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
        Db::q('CREATE TABLE IF NOT EXISTS treasury_expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
        $tCols = array_column(Db::fetchAll('PRAGMA table_info(treasury_expenses)'), 'name');
        if (!in_array('type', $tCols, true)) {
            Db::q("ALTER TABLE treasury_expenses ADD COLUMN type TEXT DEFAULT ''");
        }
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('mail_from', 'noreply@example.com')");
        Db::q('CREATE TABLE IF NOT EXISTS materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            description TEXT DEFAULT \'\',
            content TEXT DEFAULT \'\',
            image_path TEXT DEFAULT \'\',
            tag TEXT DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            updated_at TEXT
        )');
        Db::q('CREATE TABLE IF NOT EXISTS meeting_checklist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
            item TEXT NOT NULL,
            checked INTEGER NOT NULL DEFAULT 0,
            comment TEXT DEFAULT \'\',
            updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            UNIQUE (meeting_id, item)
        )');
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '9')");
        Db::q("UPDATE settings SET value = '9' WHERE key = 'schema_version'");
        Db::q("UPDATE treasury_entries SET purpose = 'Регулярный взнос' WHERE purpose = 'РегулярныйВзнос'");
        Db::q('DROP TABLE IF EXISTS service_assignments');
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '11')");
        Db::q("UPDATE settings SET value = '11' WHERE key = 'schema_version'");
        $uCols2 = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('hide_phone', $uCols2, true)) {
            Db::q('ALTER TABLE users ADD COLUMN hide_phone INTEGER NOT NULL DEFAULT 0');
        }
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '12')");
        Db::q("UPDATE settings SET value = '12' WHERE key = 'schema_version'");
        Db::q('CREATE TABLE IF NOT EXISTS vespers_readers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
            passage_key TEXT NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            UNIQUE (meeting_id, passage_key)
        )');
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '13')");
        Db::q("UPDATE settings SET value = '13' WHERE key = 'schema_version'");
        $tgCols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('telegram_chat_id', $tgCols, true)) {
            Db::q('ALTER TABLE users ADD COLUMN telegram_chat_id INTEGER DEFAULT NULL');
        }
        if (!in_array('telegram_username', $tgCols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN telegram_username TEXT DEFAULT ''");
        }
        if (!in_array('telegram_code', $tgCols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN telegram_code TEXT DEFAULT ''");
        }
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '14')");
        Db::q("UPDATE settings SET value = '14' WHERE key = 'schema_version'");
        $avCols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('avatar', $avCols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT ''");
        }
        $senCols = array_column(Db::fetchAll('PRAGMA table_info(meetings)'), 'name');
        if (!in_array('senior_user_id', $senCols, true)) {
            Db::q("ALTER TABLE meetings ADD COLUMN senior_user_id INTEGER");
        }
        $pdCols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
        if (!in_array('personal_consent', $pdCols, true)) {
            Db::q("ALTER TABLE users ADD COLUMN personal_consent INTEGER NOT NULL DEFAULT 0");
        }
        $teCols = array_column(Db::fetchAll('PRAGMA table_info(treasury_expenses)'), 'name');
        if (!in_array('to_user_id', $teCols, true)) {
            Db::q("ALTER TABLE treasury_expenses ADD COLUMN to_user_id INTEGER");
        }
        if (!in_array('to_text', $teCols, true)) {
            Db::q("ALTER TABLE treasury_expenses ADD COLUMN to_text TEXT DEFAULT ''");
        }
        $teCols2 = array_column(Db::fetchAll('PRAGMA table_info(treasury_expenses)'), 'name');
        if (!in_array('period', $teCols2, true)) {
            Db::q("ALTER TABLE treasury_expenses ADD COLUMN period TEXT DEFAULT ''");
        }
        $tentCols = array_column(Db::fetchAll('PRAGMA table_info(treasury_entries)'), 'name');
        if (!in_array('period', $tentCols, true)) {
            Db::q("ALTER TABLE treasury_entries ADD COLUMN period TEXT DEFAULT ''");
        }
        $mtCols = array_column(Db::fetchAll('PRAGMA table_info(meetings)'), 'name');
        if (!in_array('meeting_type', $mtCols, true)) {
            Db::q("ALTER TABLE meetings ADD COLUMN meeting_type TEXT DEFAULT ''");
        }
        $exCols = array_column(Db::fetchAll('PRAGMA table_info(expenses)'), 'name');
        if (!in_array('article', $exCols, true)) {
            Db::q("ALTER TABLE expenses ADD COLUMN article TEXT DEFAULT ''");
        }
        Db::q('CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            ip TEXT DEFAULT \'\',
            user_agent TEXT DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');
        Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '21')");
        Db::q("UPDATE settings SET value = '21' WHERE key = 'schema_version'");
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
    $loe = resolve_login_email($data['login_or_email'] ?? '');
    if ($loe['error']) {
        $errors[] = $loe['error'];
    }
    if (mb_strlen($data['password']) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов';
    }
    if ($data['password'] !== $data['password_confirm']) {
        $errors[] = 'Пароли не совпадают';
    }
    if (!$errors && Db::fetch(
        "SELECT id FROM users WHERE lower(login) = lower(?) OR (email != '' AND lower(email) = lower(?))",
        [$loe['login'], $loe['email']]
    )) {
        $errors[] = 'Пользователь с таким логином или email уже существует';
    }
    if ($errors) {
        return $errors;
    }

    Db::insert('users', [
        'address_term'  => $data['address_term'],
        'name'          => $data['name'],
        'phone'         => $data['phone'] ?? '',
        'birthday'      => $data['birthday'] ?? '',
        'login'         => $loe['login'],
        'email'         => $loe['email'],
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'role'          => 'Старший',
        'is_active'     => 1,
    ]);
    return [];
}