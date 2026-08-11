<?php

class AuthController
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = input('email');
            $user = Db::fetch('SELECT id, email, role FROM users WHERE lower(email) = lower(?)', [$email]);
            $error = 'Неверный email или пароль';
            if ($user && Auth::attempt($email, (string)($_POST['password'] ?? ''))) {
                set_flash('Добро пожаловать!');
                redirect(url('home'));
            }
            if ($user && !Db::value('SELECT is_active FROM users WHERE id = ?', [$user['id']])) {
                $error = 'Аккаунт отключён. Обратитесь к Старшему.';
            }
            view('login', ['title' => 'Вход', 'error' => $error], false);
            return;
        }
        view('login', ['title' => 'Вход', 'error' => null], false);
    }

    public function logout(): void
    {
        Auth::logout();
        set_flash('Вы вышли из системы', 'info');
        redirect(url('login'));
    }

    public function page_install(): void
    {
        require BASE_PATH . '/install.php';
    }
}