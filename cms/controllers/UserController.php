<?php

class UserController
{
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id ? Db::fetch('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($id && !$user) {
            http_response_code(404);
            view('errors/404', ['title' => 'Пользователь не найден']);
            return;
        }

        $prev = $_SESSION['user_form'] ?? null;
        unset($_SESSION['user_form']);
        $data = $prev ?? $user ?? [
            'address_term' => 'брат', 'name' => '', 'phone' => '', 'birthday' => '',
            'email' => '', 'telegram' => '', 'role' => 'СестраБрат', 'is_active' => 1,
        ];

        view('user_edit', [
            'title' => $user ? 'Редактирование пользователя' : 'Новый пользователь',
            'user' => $user,
            'data' => $data,
            'errors' => [],
        ]);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $addressTerm = input('address_term', 'брат');
        $name = input('name');
        $phone = input('phone');
        $birthday = input('birthday');
        $email = mb_strtolower(trim(input('email')));
        $telegram = trim(input('telegram'));
        $role = input('role');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        $errors = [];
        if (!in_array($addressTerm, ADDRESS_TERMS, true)) {
            $errors[] = 'Выберите обращение';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Укажите имя (не менее 2 символов)';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный email';
        }
        if (mb_strlen($telegram) > 64) {
            $errors[] = 'Ник в Telegram не длиннее 64 символов';
        }
        if (!in_array($role, ROLES, true)) {
            $errors[] = 'Некорректная роль';
        }
        if ($birthday !== '' && !strtotime($birthday)) {
            $errors[] = 'Некорректная дата рождения';
        }
        if (Db::fetch('SELECT id FROM users WHERE lower(email) = lower(?) AND id != ?', [$email, $id])) {
            $errors[] = 'Пользователь с таким email уже существует';
        }
        if ($password !== '') {
            if (mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Пароли не совпадают';
            }
        } elseif (!$id) {
            $errors[] = 'Укажите пароль для нового пользователя';
        }

        if ($errors) {
            $_SESSION['user_form'] = [
                'address_term' => $addressTerm, 'name' => $name, 'phone' => $phone,
                'birthday' => $birthday, 'email' => $email, 'telegram' => $telegram,
                'role' => $role, 'is_active' => $isActive,
            ];
            set_flash(implode('<br>', $errors), 'danger');
            redirect(url('user_edit', ['id' => $id ?: null]));
        }

        $data = [
            'address_term' => $addressTerm,
            'name' => $name,
            'phone' => $phone,
            'birthday' => $birthday,
            'email' => $email,
            'telegram' => $telegram,
            'role' => $role,
            'is_active' => $isActive,
        ];
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($id) {
            if (!Db::fetch('SELECT id FROM users WHERE id = ?', [$id])) {
                http_response_code(404);
                exit('Пользователь не найден');
            }
            if ($id === Auth::id() && !$isActive) {
                set_flash('Нельзя отключить собственную учётную запись', 'danger');
                redirect(url('user_edit', ['id' => $id]));
            }
            Db::update('users', $data, ['id' => $id]);
        } else {
            Db::insert('users', $data);
        }

        set_flash('Пользователь сохранён');
        redirect(url('settings', ['tab' => 'users']));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === Auth::id()) {
            set_flash('Нельзя удалить собственную учётную запись', 'danger');
            redirect(url('settings', ['tab' => 'users']));
        }
        if (!Db::fetch('SELECT id FROM users WHERE id = ?', [$id])) {
            http_response_code(404);
            exit('Пользователь не найден');
        }
        Db::delete('users', ['id' => $id]);
        set_flash('Пользователь удалён', 'info');
        redirect(url('settings', ['tab' => 'users']));
    }
}