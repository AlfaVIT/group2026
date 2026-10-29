<?php

define('BASE_PATH', __DIR__);
define('DB_PATH', BASE_PATH . '/data/cms.sqlite');
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');
define('DEFAULT_THEME', 'default');
define('THEMES', ['default', 'dark', 'light']);
define('THEME_LABELS', ['default' => 'По умолчанию', 'dark' => 'Тёмная', 'light' => 'Светлая']);
define('FONT_SIZE_MIN', 70);
define('FONT_SIZE_MAX', 150);
define('FONT_SIZE_STEP', 10);
define('CMS_INSTALLED', (function () {
    if (!is_file(DB_PATH)) {
        return false;
    }
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $row = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch();
        return (bool)$row;
    } catch (Throwable $e) {
        return false;
    }
})());