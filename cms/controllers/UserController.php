<?php

class UserController
{
    /**
     * Карточка участника: крупный аватар, имя, контакты и дата рождения.
     * AJAX-запрос получает фрагмент для модального окна, обычный — отдельную страницу.
     * Персональные данные показываются, только если участник их опубликовал
     * (дал согласие) и у смотрящего есть право view_pd.
     */
    public function card(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id > 0 ? Db::fetch(
            'SELECT id, name, address_term, avatar, role, phone, hide_phone, email, telegram,
                    birthday, personal_consent, is_active
             FROM users WHERE id = ?',
            [$id]
        ) : null;

        if (!$user || (int)$user['is_active'] !== 1) {
            http_response_code(404);
            if (is_ajax()) {
                header('Content-Type: text/html; charset=UTF-8');
                echo '<div class="cms-user-card text-center text-body-secondary py-4">Участник не найден</div>';
                return;
            }
            view('errors/404', ['title' => 'Участник не найден']);
            return;
        }

        $pdOpen = Auth::can('view_pd') && (int)$user['personal_consent'] === 1;

        $phone = $pdOpen && (int)$user['hide_phone'] === 0 ? trim((string)$user['phone']) : '';
        $email = $pdOpen ? trim((string)$user['email']) : '';
        $tgNick = $pdOpen ? telegram_nick((string)$user['telegram']) : '';

        $birthdayText = '';
        $age = null;
        $birthday = $pdOpen ? trim((string)$user['birthday']) : '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthday, $bm)) {
            $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
            $month = (int)$bm[2];
            if (isset($months[$month])) {
                $birthdayText = ltrim($bm[3], '0') . ' ' . $months[$month];
                if ($bm[1] !== '0000') {
                    $birthdayText .= ' ' . $bm[1] . ' г.';
                    $bd = DateTime::createFromFormat('!Y-m-d', $birthday);
                    if ($bd) {
                        $age = $bd->diff(new DateTime('today'))->y;
                    }
                }
            }
        }

        if ($pdOpen) {
            $pdNote = ($phone === '' && $email === '' && $tgNick === '' && $birthdayText === '')
                ? 'Участник не указал контакты и дату рождения.'
                : '';
        } elseif (!Auth::can('view_pd')) {
            $pdNote = 'Контакты и дата рождения доступны участникам группы.';
        } else {
            $pdNote = 'Участник не опубликовал контакты и дату рождения.';
        }

        $data = [
            'cardUser' => $user,
            'cardAvatar' => avatar_url($user),
            'cardPhone' => $phone,
            'cardEmail' => $email,
            'cardTelegram' => $tgNick,
            'cardTelegramLink' => $tgNick !== '' ? telegram_link((string)$user['telegram']) : '',
            'cardBirthday' => $birthdayText,
            'cardAge' => $age,
            // обычная роль участника в карточке не показывается
            'cardRole' => (string)$user['role'] === 'СестраБрат' ? '' : role_label((string)$user['role']),
            'cardPdNote' => $pdNote,
        ];

        if (is_ajax()) {
            header('Content-Type: text/html; charset=UTF-8');
            partial('user_card', $data);
            return;
        }

        view('user_card', $data + ['title' => full_name($user)]);
    }

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
            'address_term' => 'брат', 'name' => '', 'phone' => '', 'hide_phone' => 0, 'birthday' => '',
            'login' => '', 'email' => '', 'telegram' => '', 'role' => 'СестраБрат', 'is_active' => 1,
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
        $birthdayDay = input('birthday_day');
        $birthdayMonth = input('birthday_month');
        $birthdayYear = input('birthday_year');
        $birthdayNoYear = input('birthday_no_year') !== '';
        $telegram = trim(input('telegram'));
        $role = input('role');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $hidePhone = isset($_POST['hide_phone']) ? 1 : 0;
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $login = mb_strtolower(trim(input('login')));
        $email = mb_strtolower(trim(input('email')));

        $errors = [];
        if (!in_array($addressTerm, ADDRESS_TERMS, true)) {
            $errors[] = 'Выберите обращение';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Укажите имя (не менее 2 символов)';
        }
        if ($login !== '') {
            if (mb_strlen($login) < 3) {
                $errors[] = 'Логин должен быть не короче 3 символов';
            } elseif (preg_match('/[\s@]/', $login)) {
                $errors[] = 'Логин не должен содержать пробелы и символ @';
            } elseif (Db::fetch(
                'SELECT id FROM users WHERE lower(login) = lower(?) AND id != ?',
                [$login, $id]
            )) {
                $errors[] = 'Пользователь с таким логином уже существует';
            }
        } elseif ($isActive) {
            $errors[] = 'Укажите логин';
        }
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Укажите корректный email';
            } elseif (Db::fetch(
                'SELECT id FROM users WHERE lower(email) = lower(?) AND id != ?',
                [$email, $id]
            )) {
                $errors[] = 'Пользователь с таким email уже существует';
            }
        }
        if (mb_strlen($telegram) > 64) {
            $errors[] = 'Ник в Telegram не длиннее 64 символов';
        }
        if (!in_array($role, ROLES, true)) {
            $errors[] = 'Некорректная роль';
        }
        $seniorCount = (int)Db::value(
            'SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = ?' . ($id ? ' AND id != ?' : ''),
            $id ? ['Старший', $id] : ['Старший']
        );
        $becomesSenior = $role === 'Старший' && $isActive;
        if ($seniorCount === 0 && !$becomesSenior) {
            $errors[] = 'Должен остаться хотя бы один активный пользователь с ролью «Старший»';
        }
        $birthday = build_birthday($birthdayDay, $birthdayMonth, $birthdayYear, $birthdayNoYear, $birthdayError);
        if ($birthdayError) {
            $errors[] = $birthdayError;
        }
        if ($password !== '' || $passwordConfirm !== '') {
            if ($password !== $passwordConfirm) {
                $errors[] = 'Пароли не совпадают';
            } elseif (mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов';
            }
        } elseif ($isActive) {
            $hasPassword = $id
                ? (Db::fetch('SELECT password_hash FROM users WHERE id = ?', [$id])['password_hash'] ?? '') !== ''
                : false;
            if (!$hasPassword) {
                $errors[] = 'Укажите пароль для активной учётной записи без пароля';
            }
        }

        if ($errors) {
            $_SESSION['user_form'] = [
                'address_term' => $addressTerm, 'name' => $name, 'phone' => $phone,
                'hide_phone' => $hidePhone, 'birthday' => $birthday, 'login' => $login,
                'email' => $email, 'telegram' => $telegram,
                'role' => $role, 'is_active' => $isActive,
                'birthday_day' => $birthdayDay, 'birthday_month' => $birthdayMonth,
                'birthday_year' => $birthdayYear, 'birthday_no_year' => $birthdayNoYear ? 1 : 0,
            ];
            set_flash(implode('<br>', $errors), 'danger');
            redirect(url('user_edit', ['id' => $id ?: null]));
        }

        $data = [
            'address_term' => $addressTerm,
            'name' => $name,
            'phone' => $phone,
            'hide_phone' => $hidePhone,
            'birthday' => $birthday,
            'login' => $login,
            'email' => $email,
            'telegram' => $telegram,
            'role' => $role,
            'is_active' => $isActive,
        ];
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        } elseif (!$id) {
            $data['password_hash'] = '';
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
        $user = Db::fetch('SELECT avatar FROM users WHERE id = ?', [$id]);
        $materialImages = array_column(
            Db::fetchAll("SELECT image_path FROM materials WHERE user_id = ? AND image_path != ''", [$id]),
            'image_path'
        );
        Db::delete('users', ['id' => $id]);
        // материалы удаляются каскадом — подчищаем их файлы и аватар
        foreach ($materialImages as $file) {
            $path = UPLOAD_DIR . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        if (!empty($user['avatar'])) {
            $avatar = UPLOAD_DIR . '/avatars/' . $user['avatar'];
            if (is_file($avatar)) {
                @unlink($avatar);
            }
        }
        set_flash('Пользователь удалён', 'info');
        redirect(url('settings', ['tab' => 'users']));
    }
}