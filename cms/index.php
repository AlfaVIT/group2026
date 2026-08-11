<?php

require_once __DIR__ . '/app/init.php';

$needsInstall = !CMS_INSTALLED
    || (int)(Db::value('SELECT COUNT(*) FROM users') ?? 0) === 0;

if ($needsInstall) {
    if (($_GET['p'] ?? '') === 'install') {
        (new AuthController())->page_install();
    } else {
        redirect(url('install'));
    }
    exit;
}

$routes = [
    'home'            => ['HomeController', 'index', ['any']],
    'meeting'         => ['MeetingController', 'view', ['any']],
    'meeting_edit'    => ['MeetingController', 'edit', ['manage_meetings']],
    'meeting_save'    => ['MeetingController', 'save', ['manage_meetings']],
    'meeting_delete'  => ['MeetingController', 'delete', ['manage_meetings']],
    'place_edit'      => ['PlaceController', 'edit', ['manage_places']],
    'place_save'      => ['PlaceController', 'save', ['manage_places']],
    'place_delete'    => ['PlaceController', 'delete', ['manage_places']],
    'photo_delete'    => ['PlaceController', 'deletePhoto', ['manage_places']],
    'user_edit'       => ['UserController', 'edit', ['manage_users']],
    'user_save'       => ['UserController', 'save', ['manage_users']],
    'user_delete'     => ['UserController', 'delete', ['manage_users']],
    'attendance_save' => ['AttendanceController', 'save', ['mark_attendance']],
    'roles_save'      => ['RoleController', 'save', ['assign_roles']],
    'expense_add'     => ['ExpenseController', 'add', ['add_expense']],
    'expense_delete'  => ['ExpenseController', 'delete', ['any']],
    'treasury'        => ['TreasuryController', 'index', ['view_treasury']],
    'treasury_save'   => ['TreasuryController', 'save', ['view_treasury']],
    'treasury_delete' => ['TreasuryController', 'delete', ['view_treasury']],
    'settings'        => ['SettingsController', 'index', ['manage_settings']],
    'settings_save'   => ['SettingsController', 'save', ['manage_settings']],
    'profile'         => ['ProfileController', 'index', ['any']],
    'profile_save'    => ['ProfileController', 'save', ['any']],
    'login'           => ['AuthController', 'login', ['public']],
    'forgot'          => ['AuthController', 'forgot', ['public']],
    'reset'           => ['AuthController', 'reset', ['public']],
    'logout'          => ['AuthController', 'logout', ['any']],
];

$page = $_GET['p'] ?? 'home';

if (!isset($routes[$page])) {
    http_response_code(404);
    view('errors/404', ['title' => 'Страница не найдена']);
    exit;
}

[$class, $method, $roles] = $routes[$page];

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($roles === ['public']) {
    if (Auth::check()) {
        redirect(url('home'));
    }
} else {
    $user = Auth::user();
    if (!$user) {
        set_flash('Войдите, чтобы продолжить', 'warning');
        redirect(url('login'));
    }
    if ($roles !== ['any']) {
        $allowed = false;
        foreach ($roles as $perm) {
            if (Auth::can($perm)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            view('errors/403', ['title' => 'Доступ запрещён']);
            exit;
        }
    }
}

if ($isPost) {
    csrf_check();

    $saveMethod = [
        'meeting_save' => 'save',
        'place_save' => 'save',
        'user_save' => 'save',
        'attendance_save' => 'save',
        'roles_save' => 'save',
        'expense_add' => 'add',
        'expense_delete' => 'delete',
        'treasury_save' => 'save',
        'treasury_delete' => 'delete',
        'settings_save' => 'save',
        'profile_save' => 'save',
        'login' => 'login',
        'forgot' => 'forgot',
        'reset' => 'reset',
        'logout' => 'logout',
        'meeting_delete' => 'delete',
        'place_delete' => 'delete',
        'photo_delete' => 'deletePhoto',
        'user_delete' => 'delete',
    ];
    if (isset($saveMethod[$page])) {
        $method = $saveMethod[$page];
    } elseif ($method !== 'login') {
        http_response_code(405);
        exit('Метод не поддерживается');
    }
}

$controller = new $class();
$controller->$method();