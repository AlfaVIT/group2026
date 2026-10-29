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
    'calendar'        => ['HomeController', 'calendar', ['any']],
    'meeting'         => ['MeetingController', 'view', ['any']],
    'meeting_ics'     => ['MeetingController', 'ics', ['any']],
    'vespers'         => ['VespersController', 'view', ['any']],
    'vespers_readers_save' => ['VespersController', 'saveReaders', ['assign_readers']],
    'checklist_save'  => ['ChecklistController', 'save', ['mark_attendance']],
    'materials'       => ['MaterialController', 'index', ['any']],
    'material_save'   => ['MaterialController', 'save', ['any']],
    'material_delete' => ['MaterialController', 'delete', ['any']],
    'help'            => ['HelpController', 'index', ['any']],
    'stats'           => ['StatsController', 'index', ['view_stats']],
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
    'treasury'        => ['TreasuryController', 'index', ['view_treasury', 'view_treasury_graph']],
    'treasury_report' => ['TreasuryController', 'report', ['view_treasury']],
    'treasury_report_export' => ['TreasuryController', 'reportExport', ['view_treasury']],
    'treasury_save'   => ['TreasuryController', 'save', ['view_treasury']],
    'treasury_delete' => ['TreasuryController', 'delete', ['view_treasury']],
    'expense_t_edit'  => ['TreasuryController', 'addExpense', ['view_treasury']],
    'expense_t_del'   => ['TreasuryController', 'deleteExpense', ['view_treasury']],
    'settings'        => ['SettingsController', 'index', ['manage_settings']],
    'settings_save'   => ['SettingsController', 'save', ['manage_settings']],
    'telegram'        => ['TelegramController', 'index', ['manage_settings']],
    'telegram_save'   => ['TelegramController', 'save', ['manage_settings']],
    'telegram_webhook' => ['TelegramController', 'webhook', ['manage_settings']],
    'telegram_send'   => ['TelegramController', 'send', ['send_broadcast']],
    'profile'         => ['ProfileController', 'index', ['any']],
    'profile_save'    => ['ProfileController', 'save', ['any']],
    'consent'         => ['ConsentController', 'index', ['everyone']],
    'font_size_save'  => ['ProfileController', 'save', ['any']],
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
} elseif ($roles === ['everyone']) {
    // страница доступна и гостям, и авторизованным (например, текст соглашения)
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
        'expense_t_edit' => 'addExpense',
        'expense_t_del' => 'deleteExpense',
        'settings_save' => 'save',
        'telegram_save' => 'save',
        'telegram_webhook' => 'webhook',
        'telegram_send' => 'send',
        'profile_save' => 'save',
        'font_size_save' => 'save',
        'material_save' => 'save',
        'material_delete' => 'delete',
        'checklist_save' => 'save',
        'vespers_readers_save' => 'saveReaders',
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