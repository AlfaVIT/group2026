<?php

require_once __DIR__ . '/app/init.php';

if (CMS_INSTALLED && (int)(Db::value('SELECT COUNT(*) FROM users') ?? 0) > 0) {
    redirect(url('home'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    install_schema();
    $errors = install_create_admin([
        'address_term'      => input('address_term', 'брат'),
        'name'              => input('name'),
        'login_or_email'    => input('login_or_email'),
        'password'          => (string)($_POST['password'] ?? ''),
        'password_confirm'  => (string)($_POST['password_confirm'] ?? ''),
    ]);
    if ($errors) {
        $pageErrors = $errors;
    } else {
        set_flash('Установка завершена. Войдите, используя указанный логин или email и пароль.');
        redirect(url('login'));
    }
}

render_install_page($pageErrors ?? []);

function render_install_page(array $errors): void
{
    $v = urldecode(base_url());
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Установка — Группа катехизации</title>
        <link rel="icon" type="image/svg+xml" href="<?= base_url() ?>/fish.php">
        <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
        <link rel="stylesheet" href="<?= base_url() ?>/templates/default/theme.css">
    </head>
    <body class="auth-body">
    <main class="container" style="max-width: 560px; padding-top: 8vh;">
        <div class="card auth-card">
            <div class="card-body p-4">
                <div class="auth-brand">
                    <svg class="cms-fish" viewBox="0 0 24 24" width="34" height="34" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 7.5 5 12l-3 4.5"/>
                        <path d="M11 5.5c4.2 0 7.3 2.4 8.4 6.5-1.1 4.1-4.2 6.5-8.4 6.5s-7.3-2.4-8.4-6.5c1.1-4.1 4.2-6.5 8.4-6.5z"/>
                        <circle cx="16" cy="9.6" r="1.05" fill="currentColor" stroke="none"/>
                    </svg>
                    <h1 class="title">Установка системы</h1>
                    <div class="rule"></div>
                </div>
                <p class="text-body-secondary small mb-3">
                    Будет создана база данных SQLite, а указанный пользователь станет Старшим (администратором).
                </p>
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('install')) ?>" data-no-ajax>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Обращение</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="address_term" value="брат" id="at_b"
                                       <?= input('address_term', 'брат') === 'брат' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="at_b">брат</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="address_term" value="сестра" id="at_s"
                                       <?= input('address_term', 'брат') === 'сестра' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="at_s">сестра</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="name">Имя</label>
                        <input class="form-control" type="text" id="name" name="name" required value="<?= e(input('name')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="login_or_email">Логин или email</label>
                        <input class="form-control" type="text" id="login_or_email" name="login_or_email" required
                               value="<?= e(input('login_or_email')) ?>"
                               placeholder="Например: ivan или ivan@example.com">
                        <div class="form-text">
                            Можно указать email (логином станет часть до «@») или просто логин — тогда email останется пустым.
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="password">Пароль</label>
                            <input class="form-control" type="password" id="password" name="password" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="password_confirm">Повторите пароль</label>
                            <input class="form-control" type="password" id="password_confirm" name="password_confirm" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Установить</button>
                </form>
            </div>
        </div>
    </main>
    <script src="<?= base_url() ?>/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?= base_url() ?>/assets/js/app.js"></script>
    <?php partial('cookie_banner'); ?>
    </body>
    </html>
    <?php
}