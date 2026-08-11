<?php
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Новый пароль — <?= e(Setting::siteName()) ?></title>
    <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
</head>
<body class="bg-light">
<main class="container" style="max-width: 420px; padding-top: 12vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">Новый пароль</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($valid): ?>
                <p class="text-body-secondary small mb-4">Придумайте новый пароль для входа</p>
                <form method="post" action="<?= url('reset') ?>">
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
</body>
</html>
