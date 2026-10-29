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
    <title>Новый пароль — <?= e(Setting::siteName()) ?></title>
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
                <h1 class="title">Новый пароль</h1>
                <div class="rule"></div>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($valid): ?>
                <p class="text-body-secondary small mb-4">Придумайте новый пароль для входа</p>
                <form method="post" action="<?= url('reset') ?>" data-no-ajax>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">Новый пароль</label>
                        <input class="form-control" type="password" id="password" name="password" required
                               autocomplete="new-password" minlength="6">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirm">Повторите новый пароль</label>
                        <input class="form-control" type="password" id="password_confirm" name="password_confirm" required
                               autocomplete="new-password" minlength="6">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Сохранить пароль</button>
                </form>
            <?php else: ?>
                <p class="text-body-secondary small mb-4">Ссылка недействительна или истекла.</p>
                <a class="btn btn-primary w-100" href="<?= url('forgot') ?>">Запросить новую ссылку</a>
            <?php endif; ?>

            <p class="text-center mt-3 mb-0">
                <a class="small" href="<?= url('login') ?>">Вернуться ко входу</a>
            </p>
        </div>
    </div>
    <p class="text-center text-body-secondary small mt-3 mb-0">Координация деятельности группы катехизации</p>
</main>
<script src="<?= base_url() ?>/assets/vendor/jquery/jquery.min.js"></script>
<script src="<?= base_url() ?>/assets/js/app.js"></script>
<?php partial('cookie_banner'); ?>
</body>
</html>
