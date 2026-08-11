<?php
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Восстановление пароля — <?= e(Setting::siteName()) ?></title>
    <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
</head>
<body class="bg-light">
<main class="container" style="max-width: 420px; padding-top: 12vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">Восстановление пароля</h1>
            <p class="text-body-secondary small mb-4">Укажите email, на который зарегистрирована ваша учётная запись</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('forgot') ?>">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" required autofocus>
                </div>
                <button class="btn btn-primary w-100" type="submit">Отправить ссылку для восстановления</button>
            </form>
            <p class="text-center mt-3 mb-0">
                <a class="small" href="<?= url('login') ?>">Вернуться ко входу</a>
            </p>
        </div>
    </div>
    <p class="text-center text-body-secondary small mt-3 mb-0">Координация деятельности группы катехизации</p>
</main>
</body>
</html>
