<?php
$flash = get_flash();
$themeCss = theme_asset('theme.css');
$schemeCss = theme_scheme_css();
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= e(theme_scheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — <?= e(Setting::siteName()) ?></title>
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
<body class="auth-body">
<main class="container" style="max-width: 430px; padding-top: 9vh;">
    <div class="card auth-card">
        <div class="card-body p-4">
            <div class="auth-brand">
                <?php partial('fish', ['width' => 34]); ?>
                <h1 class="title"><?= e(Setting::siteName()) ?></h1>
                <div class="rule"></div>
                <p class="subtitle">Вход для участников группы</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('login') ?>" data-no-ajax>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Логин или email</label>
                    <input class="form-control" type="text" id="email" name="email" required autofocus
                           value="<?= e(input('email')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Пароль</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label small" for="remember">Запомнить меня на этом устройстве</label>
                    </div>
                    <div class="mt-2 text-end">
                        <a class="small" href="<?= url('forgot') ?>">Забыли пароль?</a>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Войти</button>
            </form>
        </div>
    </div>
    <p class="text-center text-body-secondary small mt-3 mb-0">Координация деятельности группы катехизации</p>
</main>
<script src="<?= base_url() ?>/assets/vendor/jquery/jquery.min.js"></script>
<script src="<?= base_url() ?>/assets/js/app.js"></script>
<?php partial('cookie_banner'); ?>
</body>
</html>
