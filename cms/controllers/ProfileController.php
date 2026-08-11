<?php

class ProfileController
{
    public function index(): void
    {
        view('profile', [
            'title' => 'Мой профиль',
            'user' => Auth::user(),
        ]);
    }

    public function save(): void
    {
        $action = input('action');

        if ($action === 'password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['new_password_confirm'] ?? '');
            $user = Auth::user();

            if (!password_verify($current, $user['password_hash'])) {
                set_flash('Текущий пароль указан неверно', 'danger');
                redirect(url('profile'));
            }
            if (mb_strlen($new) < 6) {
                set_flash('Новый пароль должен быть не короче 6 символов', 'danger');
                redirect(url('profile'));
            }
            if ($new !== $confirm) {
                set_flash('Новые пароли не совпадают', 'danger');
                redirect(url('profile'));
            }
            Db::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], ['id' => $user['id']]);
            set_flash('Пароль изменён');
        }

        redirect(url('profile'));
    }
}