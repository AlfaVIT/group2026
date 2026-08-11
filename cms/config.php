<?php

define('BASE_PATH', __DIR__);
define('DB_PATH', BASE_PATH . '/data/cms.sqlite');
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');
define('DEFAULT_THEME', 'default');
define('CMS_INSTALLED', is_file(DB_PATH));