<?php
$user = Auth::user();
$themeCss = theme_asset('theme.css');
$schemeCss = theme_scheme_css($user);
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= e(user_theme($user)) ?>" data-font-size="<?= (int)user_font_size($user) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? Setting::siteName()) ?> — <?= e(Setting::siteName()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url() ?>/fish.php">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
    <?php if ($themeCss): ?>
        <link rel="stylesheet" href="<?= e($themeCss) ?>">
    <?php endif; ?>
    <?php if ($schemeCss): ?>
        <link rel="stylesheet" href="<?= e($schemeCss) ?>">
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<div class="cms-topbar"></div>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= url('home') ?>">
            <?php partial('fish', ['width' => 22]); ?>
            <span><?= e(Setting::siteName()) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Меню">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('home') ?>">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('calendar') ?>">Календарь встреч</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('materials') ?>">Материалы</a>
                </li>
                <?php if (Auth::can('send_broadcast') || Auth::can('manage_settings')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('telegram') ?>">Рассылка</a>
                    </li>
                <?php endif; ?>
                <?php if (Auth::can('view_treasury') || Auth::can('view_treasury_graph')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('treasury') ?>">Казна</a>
                    </li>
                <?php endif; ?>
                <?php if (Auth::can('view_stats')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('stats') ?>">Статистика</a>
                    </li>
                <?php endif; ?>
                <?php if (Auth::can('manage_settings')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('settings') ?>">Настройки</a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                       aria-expanded="false">
                        <?= e(full_name($user)) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text small">
                                <span class="badge <?= role_badge_class($user['role']) ?>"><?= e($user['role']) ?></span>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('profile') ?>">Мой профиль</a></li>
                        <li>
                            <form method="post" action="<?= url('logout') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit">Выйти</button>
                            </form>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?= url('help') ?>" title="Справка" aria-label="Справка">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php if ($flash = get_flash()): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-0" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    </div>
<?php endif; ?>

<main class="container my-4 flex-fill">
    <?= $content ?>
</main>

<footer class="bg-dark text-light py-4 mt-auto">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small text-light-emphasis">
        <span class="cms-foot-title">
            <?php partial('fish', ['width' => 16]); ?>
            <?= e(Setting::siteName()) ?>
        </span>
        <span class="d-flex align-items-center gap-3">
            <a class="text-light-emphasis text-decoration-none" href="<?= url('help') ?>">Справка</a>
            <span>Координация деятельности группы катехизации</span>
        </span>
    </div>
</footer>

<script src="<?= base_url() ?>/assets/vendor/jquery/jquery.min.js"></script>
<script src="<?= base_url() ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url() ?>/assets/js/app.js?=v=<?= filemtime(BASE_PATH . '/assets/js/app.js') ?>"></script>
<?php partial('cookie_banner'); ?>
</body>
</html>