<?php

require_once __DIR__ . '/../app/init.php';

const MS_DATA_DIR = BASE_PATH . '/data';
const MS_PER_PAGE = 50;
const MS_SQL_ROWS = 200;
// таблицы, которые Помощник может только читать: учётные записи и настройки сайта
const MS_LOCKED_TABLES = ['users', 'settings'];

header('Content-Type: text/html; charset=UTF-8');

function ms_db_files(): array
{
    $list = [];
    foreach (glob(MS_DATA_DIR . '/*.sqlite') ?: [] as $file) {
        $list[basename($file)] = filesize($file);
    }
    ksort($list);
    return $list;
}

function ms_open_db(string $name): ?PDO
{
    $real = realpath(MS_DATA_DIR . '/' . basename($name));
    if ($real === false || !is_file($real) || strcasecmp(pathinfo($real, PATHINFO_EXTENSION), 'sqlite') !== 0) {
        return null;
    }
    $pdo = new PDO('sqlite:' . $real, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // без этого ON DELETE CASCADE из схемы не работает, а параллельная запись
    // сайта приводит к «database is locked» вместо ожидания
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function ms_ident(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function ms_link(array $params = []): string
{
    $defaults = ['db' => $_GET['db'] ?? '', 'table' => $_GET['table'] ?? '', 'action' => ''];
    $params = array_merge($defaults, $params);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

function ms_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Колонки, которые нельзя показывать открыто. */
function ms_is_secret(string $column): bool
{
    return in_array(mb_strtolower($column), ['password_hash', 'token', 'password'], true);
}

function ms_val($value, string $column = ''): string
{
    if ($value === null) {
        return '<span class="text-body-secondary fst-italic">NULL</span>';
    }
    $s = (string)$value;
    if ($column !== '' && ms_is_secret($column) && $s !== '') {
        return '<span class="ms-table-name">' . ms_esc(mb_substr($s, 0, 8)) . '…</span>'
            . ' <span class="text-body-secondary small">скрыто</span>';
    }
    if ($s !== '' && (!mb_check_encoding($s, 'UTF-8') || str_contains($s, "\0"))) {
        return '<span class="text-body-secondary fst-italic">BLOB, '
            . number_format(strlen($s), 0, ',', ' ') . ' байт</span>';
    }
    if (mb_strlen($s) > 80) {
        return '<span title="' . ms_esc($s) . '">' . ms_esc(mb_substr($s, 0, 80)) . '…</span>';
    }
    return ms_esc($s);
}

/** Имена первичного ключа в порядке объявления (поддержка составного PK). */
function ms_pk_cols(array $cols): array
{
    $pks = array_values(array_filter($cols, fn($c) => (int)$c['pk'] > 0));
    usort($pks, fn($a, $b) => (int)$a['pk'] <=> (int)$b['pk']);
    return array_column($pks, 'name');
}

/** Ключ записи в ссылке: base64 от JSON — без двойного urlencode и для любых значений. */
function ms_key_encode(array $values): string
{
    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? '' : base64_encode($json);
}

function ms_key_decode(string $param): ?array
{
    if ($param === '') {
        return null;
    }
    $raw = base64_decode($param, true);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** Значения ключа в порядке колонок; null, если ключ неполный. */
function ms_key_values(array $keyCols, array $map): ?array
{
    $values = [];
    foreach ($keyCols as $col) {
        if (!array_key_exists($col, $map)) {
            return null;
        }
        $values[] = $map[$col];
    }
    return $values;
}

function ms_key_where(array $keyCols): string
{
    return implode(' AND ', array_map(fn(string $c): string => ms_ident($c) . ' = ?', $keyCols));
}

/** Значения из формы (для повторного показа при ошибке). */
function ms_post_row(): array
{
    $row = [];
    foreach ($_POST as $key => $value) {
        if (!is_string($key) || !str_starts_with($key, 'col__')) {
            continue;
        }
        $name = substr($key, 5);
        $row[$name] = isset($_POST['null__' . $name]) ? null : (string)$value;
    }
    return $row;
}

/** Окно номеров страниц: 1 … 4 5 6 … 20. */
function ms_page_window(int $page, int $pages): array
{
    if ($pages <= 1) {
        return [];
    }
    $items = [1];
    for ($i = max(2, $page - 2); $i <= min($pages - 1, $page + 2); $i++) {
        $items[] = $i;
    }
    $items[] = $pages;
    $items = array_values(array_unique($items));
    sort($items);
    return $items;
}

/** Может ли пользователь менять данные этой таблицы. */
function ms_can_edit_table(string $table, bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }
    return !in_array(mb_strtolower($table), MS_LOCKED_TABLES, true);
}

$user = Auth::user();
$role = (string)($user['role'] ?? '');
$allowed = in_array($role, ['Старший', 'Помощник'], true);
$isAdmin = $role === 'Старший';
if (!$allowed) {
    http_response_code(403);
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Доступ запрещён</title>';
    echo '<link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css"></head><body>';
    echo '<div class="container py-5 text-center"><h1 class="h3">403</h1>';
    echo '<p class="text-body-secondary">Доступ к панели управления базой данных разрешён только Старшему и Помощнику.</p>';
    echo '<a class="btn btn-outline-primary" href="../index.php">На сайт</a></div></body></html>';
    exit;
}

$dbName = basename((string)($_GET['db'] ?? $_POST['db'] ?? ''));
$pdo = null;
$tables = [];
$tableInfo = [];
$dbFiles = ms_db_files();

if ($dbName !== '') {
    $pdo = ms_open_db($dbName);
    if ($pdo) {
        try {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $ex) {
            set_flash('Не удалось прочитать базу: ' . $ex->getMessage(), 'danger');
            $pdo = null;
            $dbName = '';
        }
    } else {
        $dbName = '';
        set_flash('База не найдена', 'danger');
    }
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $isPost
    ? (string)($_POST['action'] ?? '')
    : (string)($_GET['action'] ?? '');
if ($action === '') {
    $action = $dbName !== '' ? 'browse' : 'list';
}

$table = (string)($_GET['table'] ?? '');
$postTable = (string)($_POST['table'] ?? '');
$sqlResult = null;
$formRow = null;
$keyParam = (string)($_POST['row'] ?? $_GET['row'] ?? '');
$sql = '';

if ($isPost && !$pdo) {
    set_flash('База не выбрана или недоступна', 'danger');
    redirect('index.php');
}

if ($isPost) {
    csrf_check();
    try {
        if ($action === 'sql') {
            if (!$isAdmin) {
                set_flash('SQL-консоль доступна только Старшему', 'danger');
                redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'browse']));
            }
            $sql = (string)($_POST['sql'] ?? '');
            if (trim($sql) === '') {
                set_flash('Введите SQL-запрос', 'danger');
                redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'sql']));
            }
            $stmt = $pdo->query($sql);
            if ($stmt && $stmt->columnCount() > 0) {
                $resultCols = [];
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $meta = $stmt->getColumnMeta($i);
                    $resultCols[] = is_array($meta) && isset($meta['name']) && $meta['name'] !== ''
                        ? (string)$meta['name']
                        : 'col' . $i;
                }
                $resultRows = [];
                $truncated = false;
                while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
                    if (count($resultRows) >= MS_SQL_ROWS) {
                        $truncated = true;
                        break;
                    }
                    $resultRows[] = $row;
                }
                $sqlResult = [
                    'type' => 'select',
                    'cols' => $resultCols,
                    'rows' => $resultRows,
                    'truncated' => $truncated,
                    'sql' => $sql,
                ];
            } else {
                $affected = $stmt ? $stmt->rowCount() : 0;
                $sqlResult = ['type' => 'exec', 'affected' => $affected, 'sql' => $sql];
                set_flash(
                    $affected > 0 ? 'Запрос выполнен, затронуто строк: ' . $affected : 'Запрос выполнен, строк не изменено',
                    $affected > 0 ? 'success' : 'info'
                );
            }
            $_SESSION['ms_sql_result'] = $sqlResult;
            redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'sql']));
        }

        if (!in_array($postTable, $tables, true)) {
            set_flash('Таблица не найдена', 'danger');
            redirect(ms_link(['db' => $dbName]));
        }

        if (in_array($action, ['insert_row', 'update_row', 'delete_row'], true)
            && !ms_can_edit_table($postTable, $isAdmin)) {
            set_flash('Таблица «' . $postTable . '» доступна только для просмотра', 'danger');
            redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
        }

        $cols = $pdo->query('PRAGMA table_info(' . $pdo->quote($postTable) . ')')->fetchAll();
        $pkCols = ms_pk_cols($cols);
        $keyCols = $pkCols ?: ['rowid'];

        if ($action === 'insert_row' || $action === 'update_row') {
            $data = [];
            foreach ($cols as $col) {
                $name = $col['name'];
                $data[$name] = isset($_POST['null__' . $name])
                    ? null
                    : (string)($_POST['col__' . $name] ?? '');
            }

            if ($action === 'update_row') {
                $keyMap = ms_key_decode((string)($_POST['row'] ?? ''));
                $keyValues = $keyMap ? ms_key_values($keyCols, $keyMap) : null;
                if ($keyValues === null) {
                    set_flash('Некорректная ссылка на запись', 'danger');
                    redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
                }
                $set = [];
                $params = [];
                foreach ($data as $name => $val) {
                    if (in_array($name, $pkCols, true)) {
                        continue; // ключ не меняем
                    }
                    $set[] = ms_ident($name) . ' = ?';
                    $params[] = $val;
                }
                if (!$set) {
                    set_flash('Нет полей для изменения', 'danger');
                    redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
                }
                $params = array_merge($params, $keyValues);
                $stmt = $pdo->prepare(
                    'UPDATE ' . ms_ident($postTable) . ' SET ' . implode(', ', $set)
                    . ' WHERE ' . ms_key_where($keyCols)
                );
                $stmt->execute($params);
                $changed = $stmt->rowCount();
                set_flash(
                    $changed > 0 ? 'Запись обновлена' : 'Изменений нет — данные остались прежними',
                    $changed > 0 ? 'success' : 'info'
                );
            } else {
                $defaults = array_column($cols, 'dflt_value', 'name');
                $names = [];
                $placeholders = [];
                $params = [];
                foreach ($data as $name => $val) {
                    $hasDefault = array_key_exists($name, $defaults) && $defaults[$name] !== null;
                    if ($val === null) {
                        $names[] = ms_ident($name);
                        $placeholders[] = 'NULL';
                    } elseif ($val === '' && ($hasDefault || in_array($name, $pkCols, true))) {
                        continue; // оставляем значение по умолчанию / автоинкремент
                    } else {
                        $names[] = ms_ident($name);
                        $placeholders[] = '?';
                        $params[] = $val;
                    }
                }
                if (!$names) {
                    set_flash('Нет данных для вставки', 'danger');
                    redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'insert']));
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . ms_ident($postTable) . ' (' . implode(', ', $names)
                    . ') VALUES (' . implode(', ', $placeholders) . ')'
                );
                $stmt->execute($params);
                set_flash('Запись добавлена');
            }
            redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
        }

        if ($action === 'delete_row') {
            $keyMap = ms_key_decode((string)($_POST['row'] ?? ''));
            $keyValues = $keyMap ? ms_key_values($keyCols, $keyMap) : null;
            if ($keyValues === null) {
                set_flash('Некорректная ссылка на запись', 'danger');
                redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
            }
            $stmt = $pdo->prepare('DELETE FROM ' . ms_ident($postTable) . ' WHERE ' . ms_key_where($keyCols));
            $stmt->execute($keyValues);
            $deleted = $stmt->rowCount();
            set_flash(
                $deleted > 0 ? 'Запись удалена' : 'Запись не найдена — возможно, уже удалена',
                $deleted > 0 ? 'info' : 'warning'
            );
            redirect(ms_link(['db' => $dbName, 'table' => $postTable, 'action' => 'browse']));
        }

        http_response_code(400);
        exit('Неизвестное действие');
    } catch (PDOException $ex) {
        set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
        if ($action === 'sql') {
            $_SESSION['ms_sql_result'] = ['type' => 'error', 'sql' => $sql];
            redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'sql']));
        }
        // показываем форму снова, не теряя введённые значения
        if ($action === 'insert_row') {
            $action = 'insert';
            $formRow = ms_post_row();
        } elseif ($action === 'update_row') {
            $action = 'edit';
            $formRow = ms_post_row();
        } else {
            $action = 'browse';
        }
        $table = $postTable;
    }
}

if ($isPost && in_array($action, ['insert', 'edit', 'browse'], true)) {
    $table = $postTable !== '' ? $postTable : $table;
}
if ($table !== '' && !in_array($table, $tables, true)) {
    $table = '';
}
if (!$isAdmin && $action === 'sql') {
    set_flash('SQL-консоль доступна только Старшему', 'info');
    redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'browse']));
}
if (!$isAdmin && in_array($action, ['insert', 'edit'], true)
    && $table !== '' && !ms_can_edit_table($table, $isAdmin)) {
    set_flash('Таблица «' . $table . '» доступна только для просмотра', 'info');
    redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'browse']));
}
if (!$isPost && $action === 'sql') {
    $sqlResult = $_SESSION['ms_sql_result'] ?? null;
    unset($_SESSION['ms_sql_result']);
}

$pkCols = [];
$keyCols = [];
$hasRowid = false;
if ($pdo && $table !== '') {
    try {
        $tableInfo = $pdo->query('PRAGMA table_info(' . $pdo->quote($table) . ')')->fetchAll();
        $pkCols = ms_pk_cols($tableInfo);
        $keyCols = $pkCols ?: ['rowid'];
        $hasRowid = !$pkCols;
    } catch (PDOException $ex) {
        set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
        $table = '';
    }
}

$flash = get_flash();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MySQLite</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { background: #f6f7f9; }
        .ms-table-name { font-family: monospace; }
        .ms-fixed { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#212529;">
    <div class="container-fluid">
        <span class="navbar-brand fw-bold">MySQLite</span>
        <?php if (!$isAdmin): ?>
            <span class="badge text-bg-secondary">Помощник: без SQL</span>
        <?php endif; ?>
        <span class="navbar-text small text-light-emphasis">
            <?= $dbName !== '' ? 'База: <b>' . ms_esc($dbName) . '</b>' : '— база не выбрана —' ?>
        </span>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <form method="get" class="d-flex gap-2 mb-0">
                <select class="form-select form-select-sm" name="db" onchange="this.form.submit()">
                    <option value="">— выбрать базу —</option>
                    <?php foreach ($dbFiles as $name => $size): ?>
                        <option value="<?= ms_esc($name) ?>" <?= $name === $dbName ? 'selected' : '' ?>>
                            <?= ms_esc($name) ?> (<?= number_format($size / 1024, 0, ',', ' ') ?> КБ)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a class="btn btn-sm btn-outline-light" href="index.php">Список баз</a>
            <a class="btn btn-sm btn-outline-light" href="../index.php">На сайт</a>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= ms_esc($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= ms_esc($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    <?php endif; ?>

    <?php if ($pdo && $table !== ''): ?>
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link <?= $action === 'browse' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'browse'])) ?>">Записи</a></li>
            <li class="nav-item"><a class="nav-link <?= $action === 'structure' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'structure'])) ?>">Структура</a></li>
            <?php if ($isAdmin): ?>
                <li class="nav-item"><a class="nav-link <?= $action === 'sql' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'sql'])) ?>">SQL</a></li>
            <?php endif; ?>
            <?php if (ms_can_edit_table($table, $isAdmin)): ?>
                <li class="nav-item ms-auto">
                    <a class="nav-link fw-semibold" href="<?= ms_esc(ms_link(['action' => 'insert'])) ?>">+ Добавить запись</a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>

    <?php if ($dbName === ''): ?>
        <div class="row g-3">
            <?php foreach ($dbFiles as $name => $size): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="h6 mb-1 ms-table-name"><?= ms_esc($name) ?></h2>
                            <div class="small text-body-secondary mb-2"><?= number_format($size / 1024, 0, ',', ' ') ?> КБ</div>
                            <a class="btn btn-sm btn-primary" href="<?= ms_esc(ms_link(['db' => $name])) ?>">Открыть</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$dbFiles): ?>
                <div class="col-12"><p class="text-body-secondary">Файлы баз SQLite (*.sqlite) не найдены в каталоге data/.</p></div>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'structure'): ?>
        <?php
        $indexes = [];
        $fks = [];
        try {
            foreach ($pdo->query('PRAGMA index_list(' . $pdo->quote($table) . ')')->fetchAll() as $idx) {
                $idxCols = $pdo->query('PRAGMA index_info(' . $pdo->quote((string)$idx['name']) . ')')->fetchAll();
                $indexes[] = [
                    'name' => (string)$idx['name'],
                    'unique' => (int)$idx['unique'],
                    'origin' => (string)($idx['origin'] ?? ''),
                    'cols' => array_map(fn($c) => (string)($c['name'] ?? ''), $idxCols),
                ];
            }
            $fks = $pdo->query('PRAGMA foreign_key_list(' . $pdo->quote($table) . ')')->fetchAll();
        } catch (PDOException $ex) {
            set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
        }
        ?>
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Структура таблицы <span class="ms-table-name"><?= ms_esc($table) ?></span></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                    <tr><th>#</th><th>Колонка</th><th>Тип</th><th>NOT NULL</th><th>По умолчанию</th><th>Ключ</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tableInfo as $col): ?>
                        <tr>
                            <td><?= (int)$col['cid'] ?></td>
                            <td class="ms-table-name"><?= ms_esc($col['name']) ?></td>
                            <td class="ms-table-name"><?= ms_esc((string)$col['type']) ?></td>
                            <td><?= (int)$col['notnull'] ? 'да' : '—' ?></td>
                            <td class="ms-fixed"><?= $col['dflt_value'] === null ? '—' : ms_esc((string)$col['dflt_value']) ?></td>
                            <td><?= (int)$col['pk'] > 0 ? '<span class="badge text-bg-warning">PK</span>' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($indexes): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold">Индексы</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead><tr><th>Индекс</th><th>Колонки</th><th>Уникальный</th><th>Тип</th></tr></thead>
                        <tbody>
                        <?php foreach ($indexes as $idx): ?>
                            <tr>
                                <td class="ms-table-name"><?= ms_esc($idx['name']) ?></td>
                                <td class="ms-table-name"><?= ms_esc(implode(', ', $idx['cols'])) ?></td>
                                <td><?= $idx['unique'] ? 'да' : '—' ?></td>
                                <td class="small text-body-secondary"><?= ms_esc($idx['origin']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($fks): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold">Внешние ключи</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead><tr><th>Колонка</th><th>Таблица</th><th>Колонка</th><th>ON DELETE</th><th>ON UPDATE</th></tr></thead>
                        <tbody>
                        <?php foreach ($fks as $fk): ?>
                            <tr>
                                <td class="ms-table-name"><?= ms_esc((string)$fk['from']) ?></td>
                                <td class="ms-table-name"><?= ms_esc((string)$fk['table']) ?></td>
                                <td class="ms-table-name"><?= ms_esc((string)($fk['to'] ?? '')) ?></td>
                                <td class="small"><?= ms_esc((string)($fk['on_delete'] ?? '')) ?></td>
                                <td class="small"><?= ms_esc((string)($fk['on_update'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($action === 'sql'): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">SQL-консоль — база <span class="ms-table-name"><?= ms_esc($dbName) ?></span></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="sql">
                    <input type="hidden" name="db" value="<?= ms_esc($dbName) ?>">
                    <input type="hidden" name="table" value="<?= ms_esc($table) ?>">
                    <textarea class="form-control font-monospace" name="sql" rows="6"
                              placeholder="SELECT * FROM users LIMIT 10"><?= ms_esc($sqlResult['sql'] ?? '') ?></textarea>
                    <button class="btn btn-primary mt-2" type="submit">Выполнить</button>
                </form>
            </div>
        </div>
        <?php if (is_array($sqlResult) && ($sqlResult['type'] ?? '') === 'select'): ?>
            <?php if (!$sqlResult['rows']): ?>
                <div class="alert alert-info">Запрос выполнен, строк: 0</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead><tr><?php foreach ($sqlResult['cols'] as $c): ?><th class="ms-table-name"><?= ms_esc($c) ?></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                <?php foreach ($sqlResult['rows'] as $row): ?>
                                    <tr><?php foreach ($row as $i => $val): ?><td class="ms-fixed"><?= ms_val($val, (string)($sqlResult['cols'][$i] ?? '')) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (!empty($sqlResult['truncated'])): ?>
                        <div class="card-footer small text-body-secondary">
                            Показаны первые <?= MS_SQL_ROWS ?> строк — добавьте LIMIT к запросу, чтобы увидеть другие.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($action === 'insert' || $action === 'edit'): ?>
        <?php
        // для режима edit: ключ записи (base64 JSON), поддержка составного PK и rowid
        $row = in_array($action, ['insert'], true) ? $formRow : null;
        $editKeyValues = null;
        if ($action === 'edit') {
            $keyMap = ms_key_decode($keyParam);
            $editKeyValues = $keyMap ? ms_key_values($keyCols, $keyMap) : null;
            if ($editKeyValues === null) {
                set_flash('Некорректная ссылка на запись', 'danger');
                redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'browse']));
            }
            if ($formRow !== null) {
                $row = $formRow;
                foreach ($keyCols as $i => $col) {
                    $row[$col] = $editKeyValues[$i];
                }
            } else {
                $stmt = $pdo->prepare('SELECT t.* FROM ' . ms_ident($table) . ' t WHERE ' . ms_key_where($keyCols));
                $stmt->execute($editKeyValues);
                $row = $stmt->fetch();
                if (!$row) {
                    set_flash('Запись не найдена', 'danger');
                    redirect(ms_link(['db' => $dbName, 'table' => $table, 'action' => 'browse']));
                }
            }
        }
        ?>
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">
                <?= $action === 'edit' ? 'Редактировать запись' : 'Новая запись' ?>
                — таблица <span class="ms-table-name"><?= ms_esc($table) ?></span>
            </div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update_row' : 'insert_row' ?>">
                    <input type="hidden" name="db" value="<?= ms_esc($dbName) ?>">
                    <input type="hidden" name="table" value="<?= ms_esc($table) ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="row" value="<?= ms_esc($keyParam) ?>">
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <?php foreach ($tableInfo as $col): ?>
                                <?php
                                $name = $col['name'];
                                $value = $row ? ($row[$name] ?? '') : '';
                                $isPk = in_array($name, $pkCols, true);
                                ?>
                                <tr>
                                    <td class="col-md-3 ms-table-name">
                                        <?= ms_esc($name) ?>
                                        <?php if ($isPk): ?><span class="badge text-bg-warning">PK</span><?php endif; ?>
                                        <?php if ((int)$col['notnull']): ?><span class="text-danger">*</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input class="form-control" name="col__<?= ms_esc($name) ?>"
                                                   value="<?= ms_esc($value === null ? '' : (string)$value) ?>" <?= $isPk ? 'readonly' : '' ?>>
                                            <label class="input-group-text">
                                                <input class="form-check-input mt-0" type="checkbox" name="null__<?= ms_esc($name) ?>" value="1"
                                                       <?= $value === null ? 'checked' : '' ?>> NULL
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Сохранить' : 'Добавить' ?></button>
                    <a class="btn btn-outline-secondary" href="<?= ms_esc(ms_link(['table' => $table, 'action' => 'browse'])) ?>">Отмена</a>
                </form>
            </div>
        </div>

    <?php else: ?>
        <?php if ($table === ''): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-lg-3">
                    <div class="card shadow-sm h-100 border-primary">
                        <div class="card-body">
                            <h2 class="h6 mb-1">База</h2>
                            <div class="ms-table-name mb-2"><?= ms_esc($dbName) ?></div>
                            <a class="btn btn-sm btn-outline-primary" href="index.php">Сменить базу</a>
                        </div>
                    </div>
                </div>
                <?php foreach ($tables as $t): ?>
                    <?php
                    $count = 0;
                    try {
                        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . ms_ident($t))->fetchColumn();
                    } catch (PDOException $ex) {
                        set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
                    }
                    ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h2 class="h6 mb-1 ms-table-name"><?= ms_esc($t) ?></h2>
                                <div class="small text-body-secondary mb-2">записей: <?= $count ?></div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-primary" href="<?= ms_esc(ms_link(['table' => $t, 'action' => 'browse'])) ?>">Открыть</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= ms_esc(ms_link(['table' => $t, 'action' => 'structure'])) ?>">Структура</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$tables): ?>
                    <div class="col-12"><p class="text-body-secondary">В базе нет таблиц.</p></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($table !== ''): ?>
            <?php
            // ключ: PK (в т.ч. составной) или rowid для таблиц без PK
            $selectExtra = $hasRowid ? ', t.rowid AS rowid' : '';
            try {
                $total = (int)$pdo->query('SELECT COUNT(*) FROM ' . ms_ident($table))->fetchColumn();
                $pages = max(1, (int)ceil($total / MS_PER_PAGE));
                $page = max(1, min((int)($_GET['page'] ?? 1), $pages));
                $rows = $pdo->query(
                    'SELECT t.*' . $selectExtra . ' FROM ' . ms_ident($table) . ' t'
                    . ' ORDER BY ' . ms_ident($keyCols[0])
                    . ' LIMIT ' . MS_PER_PAGE . ' OFFSET ' . (($page - 1) * MS_PER_PAGE)
                )->fetchAll();
            } catch (PDOException $ex) {
                set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
                $total = 0;
                $pages = 1;
                $page = 1;
                $rows = [];
            }
            $pageWindow = ms_page_window($page, $pages);
            ?>
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Таблица <span class="ms-table-name"><?= ms_esc($table) ?></span>
                        <span class="small text-body-secondary ms-2">всего: <?= $total ?></span>
                        <?php if (!ms_can_edit_table($table, $isAdmin)): ?>
                            <span class="badge text-bg-secondary ms-1" title="Изменение доступно только Старшему">только чтение</span>
                        <?php endif; ?></span>
                    <span class="small text-body-secondary d-flex align-items-center gap-1">
                        <?php $prevPage = 0; ?>
                        <?php foreach ($pageWindow as $i): ?>
                            <?php if ($prevPage && $i > $prevPage + 1): ?><span class="text-body-tertiary">…</span><?php endif; ?>
                            <?php if ($i === $page): ?>
                                <b class="text-body"><?= $i ?></b>
                            <?php else: ?>
                                <a href="<?= ms_esc(ms_link(['table' => $table, 'action' => 'browse', 'page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                            <?php $prevPage = $i; ?>
                        <?php endforeach; ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                            <tr>
                                <th></th>
                                <?php foreach ($tableInfo as $col): ?>
                                    <th class="ms-table-name"><?= ms_esc($col['name']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="<?= count($tableInfo) + 1 ?>" class="text-body-secondary">Записей нет.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $keyMap = [];
                                foreach ($keyCols as $col) {
                                    $keyMap[$col] = $r[$col] ?? null;
                                }
                                $rowKey = ms_key_encode($keyMap);
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?php if (ms_can_edit_table($table, $isAdmin)): ?>
                                            <a class="btn btn-sm btn-outline-primary py-0" title="Редактировать"
                                               href="<?= ms_esc(ms_link(['table' => $table, 'action' => 'edit', 'row' => $rowKey])) ?>">✎</a>
                                            <form class="d-inline" method="post" onsubmit="return confirm('Удалить запись?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_row">
                                                <input type="hidden" name="db" value="<?= ms_esc($dbName) ?>">
                                                <input type="hidden" name="table" value="<?= ms_esc($table) ?>">
                                                <input type="hidden" name="row" value="<?= ms_esc($rowKey) ?>">
                                                <button class="btn btn-sm btn-outline-danger py-0" type="submit" title="Удалить">✕</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-body-tertiary" title="Только просмотр">🔒</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($tableInfo as $col): ?>
                                        <td class="ms-fixed"><?= ms_val($r[$col['name']], $col['name']) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
