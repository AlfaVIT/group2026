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
    remember_token TEXT DEFAULT '',
    remember_expires TEXT,
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

CREATE TABLE IF NOT EXISTS treasury_articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL,
    key TEXT NOT NULL,
    name TEXT NOT NULL,
    archived INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (kind, key)
);

CREATE TABLE IF NOT EXISTS treasury_article_pairs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL,
    key_a TEXT NOT NULL,
    key_b TEXT NOT NULL,
    UNIQUE (kind, key_a, key_b)
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

CREATE TABLE IF NOT EXISTS meeting_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT DEFAULT '',
    visibility TEXT NOT NULL DEFAULT 'private',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (meeting_id, user_id)
);

CREATE TABLE IF NOT EXISTS meeting_note_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    note_id INTEGER NOT NULL REFERENCES meeting_notes(id) ON DELETE CASCADE,
    kind TEXT NOT NULL DEFAULT 'image',
    file_path TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;

    $pdo->exec($schema);

    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('site_name', 'Группа катехизации')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('theme', '" . DEFAULT_THEME . "')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('mail_from', 'noreply@example.com')");
    $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '25')");

    // предустановленные статьи расходов
    foreach (EXPENSE_ARTICLES as $preset) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO treasury_articles (kind, key, name) VALUES (?, ?, ?)');
        $stmt->execute(['expense', article_key($preset), $preset]);
    }
}

function run_migrations(): void
{
    if (!is_file(DB_PATH)) {
        return;
    }
    try {
        $version = (int)(Db::value('SELECT value FROM settings WHERE key = ?', ['schema_version']) ?? 0);
        if ($version >= 25) {
            return;
        }
        if ($version >= 21) {
            migrate_v22();
            migrate_v23();
            migrate_v24();
            migrate_v25();
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
        migrate_v22();
        migrate_v23();
        migrate_v24();
        migrate_v25();
    } catch (Throwable $e) {
    }
}

/** v23: единое написание статей казны (регистр, лишние пробелы). */
function migrate_v23(): void
{
    foreach ([['treasury_entries', 'purpose'], ['treasury_expenses', 'type']] as [$table, $column]) {
        $rows = Db::fetchAll(
            "SELECT $column AS name, COUNT(*) AS cnt FROM $table
             WHERE TRIM($column) != '' GROUP BY $column"
        );
        $groups = [];
        foreach ($rows as $r) {
            $groups[article_key((string)$r['name'])][(string)$r['name']] = (int)$r['cnt'];
        }
        foreach ($groups as $variants) {
            // целевое написание: уже канонический вариант, иначе самый частый
            $target = null;
            foreach (array_keys($variants) as $spelling) {
                if (article_canonical($spelling) === $spelling) {
                    $target = $spelling;
                    break;
                }
            }
            if ($target === null) {
                arsort($variants);
                $target = article_canonical((string)array_key_first($variants));
            }
            foreach (array_keys($variants) as $spelling) {
                if ($spelling !== $target) {
                    Db::q("UPDATE $table SET $column = ? WHERE $column = ?", [$target, $spelling]);
                }
            }
        }
    }
    Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '23')");
    Db::q("UPDATE settings SET value = '23' WHERE key = 'schema_version'");
}

/** v24: справочник статей казны + пары «это разные статьи». */
function migrate_v24(): void
{
    Db::q('CREATE TABLE IF NOT EXISTS treasury_articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        key TEXT NOT NULL,
        name TEXT NOT NULL,
        archived INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        UNIQUE (kind, key)
    )');
    Db::q('CREATE TABLE IF NOT EXISTS treasury_article_pairs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        key_a TEXT NOT NULL,
        key_b TEXT NOT NULL,
        UNIQUE (kind, key_a, key_b)
    )');

    // переносим существующие статьи из записей казны
    foreach ([['income', 'treasury_entries', 'purpose'], ['expense', 'treasury_expenses', 'type']] as [$kind, $table, $column]) {
        foreach (collect_articles($table, $column) as $key => $name) {
            Db::q(
                'INSERT OR IGNORE INTO treasury_articles (kind, key, name) VALUES (?, ?, ?)',
                [$kind, $key, $name]
            );
        }
    }
    // предустановленные статьи расходов
    foreach (EXPENSE_ARTICLES as $preset) {
        Db::q(
            'INSERT OR IGNORE INTO treasury_articles (kind, key, name) VALUES (?, ?, ?)',
            ['expense', article_key($preset), $preset]
        );
    }

    Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '24')");
    Db::q("UPDATE settings SET value = '24' WHERE key = 'schema_version'");
}

/** v25: конспекты встреч (текст, изображения, аудио) с приватностью. */
function migrate_v25(): void
{
    Db::q('CREATE TABLE IF NOT EXISTS meeting_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        body TEXT DEFAULT \'\',
        visibility TEXT NOT NULL DEFAULT \'private\',
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        updated_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
        UNIQUE (meeting_id, user_id)
    )');
    Db::q('CREATE TABLE IF NOT EXISTS meeting_note_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id INTEGER NOT NULL REFERENCES meeting_notes(id) ON DELETE CASCADE,
        kind TEXT NOT NULL DEFAULT \'image\',
        file_path TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )');
    Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '25')");
    Db::q("UPDATE settings SET value = '25' WHERE key = 'schema_version'");
}

/** v22: токен «запомнить меня» в users (см. Auth::issueRemember). */
function migrate_v22(): void
{
    $cols = array_column(Db::fetchAll('PRAGMA table_info(users)'), 'name');
    if (!in_array('remember_token', $cols, true)) {
        Db::q("ALTER TABLE users ADD COLUMN remember_token TEXT DEFAULT ''");
    }
    if (!in_array('remember_expires', $cols, true)) {
        Db::q('ALTER TABLE users ADD COLUMN remember_expires TEXT');
    }
    Db::q("INSERT OR IGNORE INTO settings (key, value) VALUES ('schema_version', '22')");
    Db::q("UPDATE settings SET value = '22' WHERE key = 'schema_version'");
}

/** Проверка данных администратора без обращения к БД (можно вызывать до install_schema). */
function install_validate_admin_data(array $data): array
{
    $errors = [];
    if (!in_array($data['address_term'] ?? '', ADDRESS_TERMS, true)) {
        $errors[] = 'Выберите обращение (брат или сестра)';
    }
    if (mb_strlen((string)($data['name'] ?? '')) < 2) {
        $errors[] = 'Укажите имя (не менее 2 символов)';
    }
    $loe = resolve_login_email((string)($data['login_or_email'] ?? ''));
    if ($loe['error']) {
        $errors[] = $loe['error'];
    }
    if (mb_strlen((string)($data['password'] ?? '')) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов';
    }
    if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
        $errors[] = 'Пароли не совпадают';
    }
    return $errors;
}

function install_create_admin(array $data): array
{
    $errors = install_validate_admin_data($data);
    $loe = resolve_login_email($data['login_or_email'] ?? '');
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