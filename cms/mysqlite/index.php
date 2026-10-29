<?php

require_once __DIR__ . '/../app/init.php';

const MS_DATA_DIR = BASE_PATH . '/data';
const MS_PER_PAGE = 50;

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
    return new PDO('sqlite:' . $real, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function ms_ident(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function ms_link(array $params = []): string
{
    $defaults = ['db' => $_GET['db'] ?? '', 'table' => $_GET['table'] ?? '', 'action' => ''];
    $params = array_merge($defaults, $params);
    $qs = http_build_query(array_filter($params, fn($v) => $v !== ''));
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

function ms_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ms_val($value): string
{
    if ($value === null) {
        return '<span class="text-body-secondary fst-italic">NULL</span>';
    }
    $s = (string)$value;
    if (mb_strlen($s) > 80) {
        return '<span title="' . ms_esc($s) . '">' . ms_esc(mb_substr($s, 0, 80)) . '…</span>';
    }
    return ms_esc($s);
}

$user = Auth::user();
$allowed = $user && in_array($user['role'], ['Старший', 'Помощник'], true);
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
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $dbName = '';
        set_flash('База не найдена', 'danger');
    }
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? ($dbName !== '' ? 'browse' : 'list'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ($action === 'sql') {
            $sql = (string)($_POST['sql'] ?? '');
            if (trim($sql) === '') {
                set_flash('Введите SQL-запрос', 'danger');
            } else {
                $stmt = $pdo->query($sql);
                if ($stmt && $stmt->columnCount() > 0) {
                    $sqlResult = ['cols' => [], 'rows' => $stmt->fetchAll()];
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $sqlResult['cols'][] = $stmt->getColumnMeta($i)['name'] ?? ('col' . $i);
                    }
                } else {
                    $sqlResult = ['affected' => $stmt ? $stmt->rowCount() : $pdo->exec($sql)];
                }
            }
        } elseif ($action === 'insert_row' || $action === 'update_row') {
            $table = (string)($_POST['table'] ?? '');
            if (!in_array($table, $tables, true)) {
                set_flash('Таблица не найдена', 'danger');
            } else {
                $cols = $pdo->query('PRAGMA table_info(' . $pdo->quote($table) . ')')->fetchAll();
                $data = [];
                foreach ($cols as $col) {
                    $name = $col['name'];
                    if (isset($_POST['null__' . $name])) {
                        $data[$name] = null;
                    } else {
                        $data[$name] = $_POST['col__' . $name] ?? '';
                    }
                }
                $pks = array_values(array_filter($cols, fn($c) => (int)$c['pk'] > 0));
                if ($action === 'update_row') {
                    $keyCol = $pks ? $pks[0]['name'] : '_rowid';
                    $keyVal = (string)($_POST['pk_val'] ?? '');
                    if ($keyVal === '') {
                        set_flash('Не указан ключ записи', 'danger');
                    } else {
                        $set = [];
                        $params = [];
                        foreach ($data as $name => $val) {
                            if ($name === $keyCol) {
                                continue;
                            }
                            $set[] = ms_ident($name) . ' = ?';
                            $params[] = $val;
                        }
                        $params[] = $keyVal;
                        $pdo->prepare('UPDATE ' . ms_ident($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . ms_ident($keyCol) . ' = ?')->execute($params);
                        set_flash('Запись обновлена');
                    }
                } else {
                    $defaults = array_column($cols, 'dflt_value', 'name');
                    $pksCols = array_column(array_filter($cols, fn($c) => (int)$c['pk'] > 0), 'name');
                    $names = [];
                    $placeholders = [];
                    $params = [];
                    foreach ($data as $name => $val) {
                        if ($val === null) {
                            $names[] = ms_ident($name);
                            $placeholders[] = 'NULL';
                        } elseif ($val === '' && (($defaults[$name] ?? null) !== null || in_array($name, $pksCols, true))) {
                            continue;
                        } else {
                            $names[] = ms_ident($name);
                            $placeholders[] = '?';
                            $params[] = $val;
                        }
                    }
                    if ($names) {
                        $pdo->prepare('INSERT INTO ' . ms_ident($table) . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')')->execute($params);
                        set_flash('Запись добавлена');
                    } else {
                        set_flash('Нет данных для вставки', 'danger');
                    }
                }
                redirect(ms_link(['table' => $table, 'action' => 'browse']));
            }
        } elseif ($action === 'delete_row') {
            $table = (string)($_POST['table'] ?? '');
            if (!in_array($table, $tables, true)) {
                set_flash('Таблица не найдена', 'danger');
            } else {
                $cols = $pdo->query('PRAGMA table_info(' . $pdo->quote($table) . ')')->fetchAll();
                $pks = array_values(array_filter($cols, fn($c) => (int)$c['pk'] > 0));
                $keyCol = $pks ? $pks[0]['name'] : '_rowid';
                $pdo->prepare('DELETE FROM ' . ms_ident($table) . ' WHERE ' . ms_ident($keyCol) . ' = ?')->execute([(string)($_POST['pk_val'] ?? '')]);
                set_flash('Запись удалена', 'info');
                redirect(ms_link(['table' => $table, 'action' => 'browse']));
            }
        } else {
            http_response_code(400);
            exit('Неизвестное действие');
        }
    } catch (PDOException $ex) {
        set_flash('Ошибка: ' . $ex->getMessage(), 'danger');
    }
}

$table = (string)($_GET['table'] ?? '');
if ($pdo && $table !== '' && !in_array($table, $tables, true)) {
    $table = '';
}

if ($pdo && $table !== '') {
    $tableInfo = $pdo->query('PRAGMA table_info(' . $pdo->quote($table) . ')')->fetchAll();
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
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    <?php endif; ?>

    <?php if ($pdo && $table !== ''): ?>
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link <?= $action === 'browse' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'browse'])) ?>">Записи</a></li>
            <li class="nav-item"><a class="nav-link <?= $action === 'structure' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'structure'])) ?>">Структура</a></li>
            <li class="nav-item"><a class="nav-link <?= $action === 'sql' ? 'active' : '' ?>" href="<?= ms_esc(ms_link(['action' => 'sql'])) ?>">SQL</a></li>
            <li class="nav-item ms-auto">
                <a class="nav-link fw-semibold" href="<?= ms_esc(ms_link(['action' => 'insert'])) ?>">+ Добавить запись</a>
            </li>
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

    <?php elseif ($action === 'sql'): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">SQL-консоль — база <span class="ms-table-name"><?= ms_esc($dbName) ?></span></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="sql">
                    <input type="hidden" name="db" value="<?= ms_esc($dbName) ?>">
                    <textarea class="form-control font-monospace" name="sql" rows="6"
                              placeholder="SELECT * FROM users LIMIT 10"><?= ms_esc($_POST['sql'] ?? '') ?></textarea>
                    <button class="btn btn-primary mt-2" type="submit">Выполнить</button>
                </form>
            </div>
        </div>
        <?php if (!empty($sqlResult)): ?>
            <?php if (!empty($sqlResult['affected'])): ?>
                <div class="alert alert-success">Запрос выполнен, затронуто строк: <?= (int)$sqlResult['affected'] ?></div>
            <?php elseif (!empty($sqlResult['rows'])): ?>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead><tr><?php foreach ($sqlResult['cols'] as $c): ?><th class="ms-table-name"><?= ms_esc($c) ?></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                <?php foreach ($sqlResult['rows'] as $row): ?>
                                    <tr><?php foreach ($row as $val): ?><td class="ms-fixed"><?= ms_val($val) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif (isset($sqlResult['rows'])): ?>
                <div class="alert alert-info">Запрос выполнен, строк: 0</div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($action === 'insert' || $action === 'edit'): ?>
        <?php
        $row = null;
        $keyCol = null;
        $pks = array_values(array_filter($tableInfo, fn($c) => (int)$c['pk'] > 0));
        if ($action === 'edit') {
            $keyCol = $pks ? $pks[0]['name'] : '_rowid';
            $keyVal = (string)($_GET['row'] ?? '');
            $row = $pdo->query(
                'SELECT t.*, t.rowid AS _rowid FROM ' . ms_ident($table) . ' t WHERE ' . ms_ident($keyCol) . ' = ' . $pdo->quote($keyVal)
            )->fetch();
            if (!$row) {
                set_flash('Запись не найдена', 'danger');
                redirect(ms_link(['table' => $table, 'action' => 'browse']));
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
                        <input type="hidden" name="pk_val" value="<?= ms_esc((string)$row[$keyCol]) ?>">
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <?php foreach ($tableInfo as $col): ?>
                                <?php
                                $name = $col['name'];
                                $value = $row ? ($row[$name] ?? '') : '';
                                $isPk = (int)$col['pk'] > 0;
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
                                                   value="<?= ms_esc($value === null ? '' : (string)$value) ?>" <?= $isPk && $action === 'edit' ? 'readonly' : '' ?>>
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
                    $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . ms_ident($t))->fetchColumn();
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
            $pks = array_values(array_filter($tableInfo, fn($c) => (int)$c['pk'] > 0));
            $keyCol = $pks ? $pks[0]['name'] : '_rowid';
            $total = (int)$pdo->query('SELECT COUNT(*) FROM ' . ms_ident($table))->fetchColumn();
            $pages = max(1, (int)ceil($total / MS_PER_PAGE));
            $page = max(1, min((int)($_GET['page'] ?? 1), $pages));
            $rows = $pdo->query(
                'SELECT t.*, t.rowid AS _rowid FROM ' . ms_ident($table) . ' t ORDER BY ' . ms_ident($keyCol) . ' LIMIT ' . MS_PER_PAGE . ' OFFSET ' . (($page - 1) * MS_PER_PAGE)
            )->fetchAll();
            ?>
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Таблица <span class="ms-table-name"><?= ms_esc($table) ?></span>
                        <span class="small text-body-secondary ms-2">всего: <?= $total ?></span></span>
                    <span class="small text-body-secondary">
                        <?php if ($pages > 1): ?>
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <?php if ($i === $page): ?>
                                    <b class="text-body"><?= $i ?></b>
                                <?php else: ?>
                                    <a href="<?= ms_esc(ms_link(['table' => $table, 'action' => 'browse', 'page' => $i])) ?>"><?= $i ?></a>
                                <?php endif; ?> 
                            <?php endfor; ?>
                        <?php endif; ?>
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
                                <tr>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary py-0" title="Редактировать"
                                           href="<?= ms_esc(ms_link(['table' => $table, 'action' => 'edit', 'row' => urlencode((string)$r[$keyCol])])) ?>">✎</a>
                                        <form class="d-inline" method="post" onsubmit="return confirm('Удалить запись?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_row">
                                            <input type="hidden" name="db" value="<?= ms_esc($dbName) ?>">
                                            <input type="hidden" name="table" value="<?= ms_esc($table) ?>">
                                            <input type="hidden" name="pk_val" value="<?= ms_esc((string)$r[$keyCol]) ?>">
                                            <button class="btn btn-sm btn-outline-danger py-0" type="submit">✕</button>
                                        </form>
                                    </td>
                                    <?php foreach ($tableInfo as $col): ?>
                                        <td class="ms-fixed"><?= ms_val($r[$col['name']]) ?></td>
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
