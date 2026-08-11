<?php

require_once __DIR__ . '/app/init.php';

if (CMS_INSTALLED) {
    redirect(url('home'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    install_schema();
    $errors = install_create_admin([
        'address_term'      => input('address_term', 'брат'),
        'name'              => input('name'),
        'phone'             => input('phone'),
        'birthday'          => input('birthday'),
        'email'             => input('email'),
        'password'          => (string)($_POST['password'] ?? ''),
        'password_confirm'  => (string)($_POST['password_confirm'] ?? ''),
    ]);
    if ($errors) {
        $pageErrors = $errors;
    } else {
        set_flash('Установка завершена. Войдите, используя указанный email и пароль.');
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
        <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
    </head>
    <body class="bg-light">
    <main class="container" style="max-width: 560px; padding-top: 8vh;">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Установка системы</h1>
                <p class="text-body-secondary small mb-3">
                    Будет создана база данных SQLite, а указанный пользователь станет Старшим (администратором).
                </p>
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('install')) ?>">
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
                        <label class="form-label" for="phone">Телефон</label>
                        <input class="form-control" type="text" id="phone" name="phone" value="<?= e(input('phone')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="birthday">День рождения</label>
                        <input class="form-control" type="date" id="birthday" name="birthday" value="<?= e(input('birthday')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email (логин)</label>
                        <input class="form-control" type="email" id="email" name="email" required value="<?= e(input('email')) ?>">
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
    </body>
    </html>
    <?php
}