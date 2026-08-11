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

    public function forgot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = mb_strtolower(trim(input('email')));
            $user = Db::fetch('SELECT * FROM users WHERE lower(email) = lower(?) AND is_active = 1', [$email]);
            if ($user) {
                $token = bin2hex(random_bytes(32));
                Db::q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
                Db::insert('password_resets', [
                    'user_id'    => $user['id'],
                    'token'      => $token,
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                ]);
                $link = url('reset', ['token' => $token]);
                $html = '<p>Здравствуйте, ' . e(full_name($user)) . '!</p>'
                    . '<p>Для восстановления пароля на сайте «' . e(Setting::siteName()) . '» перейдите по ссылке:</p>'
                    . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
                    . '<p>Ссылка действительна в течение 1 часа.</p>'
                    . '<p>Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.</p>';
                $sent = send_email($user['email'], 'Восстановление пароля — ' . Setting::siteName(), $html);
                if ($sent) {
                    set_flash('Если пользователь с таким email существует, на него отправлена ссылка для восстановления пароля', 'info');
                } else {
                    set_flash('Email не настроен на этом сервере. Воспользуйтесь ссылкой восстановления: ' . e($link), 'warning');
                }
            } else {
                set_flash('Если пользователь с таким email существует, на него отправлена ссылка для восстановления пароля', 'info');
            }
            redirect(url('login'));
        }
        view('forgot', ['title' => 'Восстановление пароля', 'error' => null], false);
    }

    public function reset(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        $row = Db::fetch(
            'SELECT pr.*, u.email FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > datetime(\'now\')',
            [$token]
        );
        if (!$row) {
            view('reset', ['title' => 'Восстановление пароля', 'token' => '', 'valid' => false, 'error' => 'Ссылка недействительна или истекла. Запросите восстановление пароля заново.'], false);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string)($_POST['password'] ?? '');
            $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
            $error = null;
            if (mb_strlen($password) < 6) {
                $error = 'Пароль должен быть не короче 6 символов';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Пароли не совпадают';
            }
            if ($error) {
                view('reset', ['title' => 'Восстановление пароля', 'token' => $token, 'valid' => true, 'error' => $error], false);
                return;
            }
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            Db::q('UPDATE password_resets SET used = 1 WHERE id = ?', [$row['id']]);
            set_flash('Пароль изменён. Войдите с новым паролем.');
            redirect(url('login'));
        }

        view('reset', ['title' => 'Восстановление пароля', 'token' => $token, 'valid' => true, 'error' => null], false);
    }
}