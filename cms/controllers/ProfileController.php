<?php

class ProfileController
{
    public function index(): void
    {
        $user = Auth::user();
        $pdConsent = (int)($user['personal_consent'] ?? 0) === 1;
        if (!$pdConsent) {
            // персональные данные не показываются и не редактируются до получения согласия
            $user['phone'] = '';
            $user['email'] = '';
            $user['birthday'] = '';
            $user['telegram'] = '';
        }
        view('profile', [
            'title' => 'Мой профиль',
            'user' => $user,
            'pdConsent' => $pdConsent,
            'device' => visitor_device((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'deviceUa' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'tgConfigured' => Telegram::configured(),
            'tgLinked' => (int)($user['telegram_chat_id'] ?? 0) > 0,
            'tgUsername' => (string)($user['telegram_username'] ?? ''),
            'tgLink' => telegram_activation_link((int)$user['id']),
        ]);
    }

    public function save(): void
    {
        $action = input('action');

        if ($action === 'data') {
            $phone = input('phone');
            $hidePhone = isset($_POST['hide_phone']) ? 1 : 0;
            $telegram = trim(input('telegram'));
            $email = mb_strtolower(trim(input('email')));
            $login = mb_strtolower(trim(input('login')));
            $name = trim(input('name'));
            $birthday = build_birthday(
                input('birthday_day'),
                input('birthday_month'),
                input('birthday_year'),
                input('birthday_no_year') !== '',
                $birthdayError
            );
            $id = Auth::id();
            $consented = (int)(Auth::user()['personal_consent'] ?? 0) === 1;

            if (mb_strlen($login) < 3 || preg_match('/[\s@]/', $login)) {
                set_flash('Логин должен быть не короче 3 символов и не содержать пробелы и символ @', 'danger');
                redirect(url('profile'));
            }
            if (mb_strlen($name) < 2) {
                set_flash('Укажите имя (не менее 2 символов)', 'danger');
                redirect(url('profile'));
            }
            if (Db::fetch(
                'SELECT id FROM users WHERE lower(login) = lower(?) AND id != ?',
                [$login, $id]
            )) {
                set_flash('Пользователь с таким логином уже существует', 'danger');
                redirect(url('profile'));
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('Укажите корректный email', 'danger');
                redirect(url('profile'));
            }
            if ($email !== '' && Db::fetch(
                'SELECT id FROM users WHERE lower(email) = lower(?) AND id != ?',
                [$email, $id]
            )) {
                set_flash('Пользователь с таким email уже существует', 'danger');
                redirect(url('profile'));
            }
            if (mb_strlen($telegram) > 64) {
                set_flash('Ник в Telegram не длиннее 64 символов', 'danger');
                redirect(url('profile'));
            }
            if ($birthdayError) {
                set_flash($birthdayError, 'danger');
                redirect(url('profile'));
            }

            if (!$consented) {
                // без согласия персональные данные не сохраняются
                $phone = '';
                $email = '';
                $telegram = '';
                $birthday = '';
            }

            Db::update('users', [
                'login' => $login,
                'name' => $name,
                'phone' => $phone,
                'hide_phone' => $hidePhone,
                'telegram' => $telegram,
                'email' => $email,
                'birthday' => $birthday,
            ], ['id' => $id]);
            set_flash('Данные сохранены');
            redirect(url('profile'));
        }

        if ($action === 'address_term') {
            $term = input('address_term');
            if (!in_array($term, ADDRESS_TERMS, true)) {
                set_flash('Выберите обращение', 'danger');
                redirect(url('profile'));
            }
            Db::update('users', ['address_term' => $term], ['id' => Auth::id()]);
            set_flash('Обращение сохранено');
            redirect(url('profile'));
        }

        if ($action === 'font_size') {
            $size = (int)($_POST['font_size'] ?? 100);
            $size = max(FONT_SIZE_MIN, min(FONT_SIZE_MAX, (int)round($size / FONT_SIZE_STEP) * FONT_SIZE_STEP));
            Db::update('users', ['font_size' => $size], ['id' => Auth::id()]);
            set_flash('Размер текста: ' . $size . '%');
            redirect(url('profile'));
        }

        if ($action === 'theme') {
            $theme = input('theme');
            if (!in_array($theme, THEMES, true)) {
                $theme = '';
            }
            Db::update('users', ['theme' => $theme], ['id' => Auth::id()]);
            set_flash($theme === ''
                ? 'Тема оформления: как на сайте'
                : 'Тема оформления: ' . THEME_LABELS[$theme]);
            redirect(url('profile'));
        }

        if ($action === 'pd_consent') {
            Db::update('users', ['personal_consent' => 1], ['id' => Auth::id()]);
            pd_consent_cookie(true);
            set_flash('Согласие на обработку персональных данных предоставлено');
            redirect(url('profile'));
        }

        if ($action === 'pd_revoke') {
            $id = Auth::id();
            Db::update('users', [
                'personal_consent' => 0,
                'phone' => '',
                'email' => '',
                'birthday' => '',
                'telegram' => '',
                'telegram_chat_id' => null,
                'telegram_username' => '',
            ], ['id' => $id]);
            pd_consent_cookie(false);
            set_flash('Согласие отозвано. Персональные данные (телефон, email, дата рождения, Telegram) удалены.', 'info');
            redirect(url('profile'));
        }

        if ($action === 'avatar') {
            $user = Auth::user();
            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                set_flash('Выберите файл с изображением', 'danger');
                redirect(url('profile'));
            }
            $tmp = $_FILES['avatar']['tmp_name'];
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed, true)) {
                set_flash('Загрузите изображение (JPG, PNG, GIF или WebP)', 'danger');
                redirect(url('profile'));
            }
            if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                set_flash('Изображение не должно быть больше 5 МБ', 'danger');
                redirect(url('profile'));
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
            $filename = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dir = UPLOAD_DIR . '/avatars';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (!$this->saveAvatarImage($tmp, $mime, $dir . '/' . $filename)) {
                set_flash('Не удалось сохранить изображение', 'danger');
                redirect(url('profile'));
            }
            $old = (string)($user['avatar'] ?? '');
            Db::update('users', ['avatar' => $filename], ['id' => Auth::id()]);
            if ($old !== '' && $old !== $filename) {
                @unlink(UPLOAD_DIR . '/avatars/' . $old);
            }
            set_flash('Аватар сохранён');
            redirect(url('profile'));
        }

        if ($action === 'avatar_remove') {
            $user = Auth::user();
            $old = (string)($user['avatar'] ?? '');
            Db::update('users', ['avatar' => ''], ['id' => Auth::id()]);
            if ($old !== '') {
                @unlink(UPLOAD_DIR . '/avatars/' . $old);
            }
            set_flash('Аватар удалён', 'info');
            redirect(url('profile'));
        }

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

    /**
     * Сохраняет изображение аватара квадратом 100x100: масштабирует до 100px
     * по меньшей стороне, затем обрезает большую сторону до 100px с
     * центрированием по середине. Если GD недоступен или формат не
     * поддерживается — сохраняет как есть.
     */
    private function saveAvatarImage(string $src, string $mime, string $dst): bool
    {
        if (!extension_loaded('gd')) {
            return move_uploaded_file($src, $dst);
        }
        $creators = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
            'image/gif'  => 'imagecreatefromgif',
            'image/webp' => 'imagecreatefromwebp',
        ];
        $create = $creators[$mime] ?? '';
        if ($create === '' || !function_exists($create)) {
            return move_uploaded_file($src, $dst);
        }
        $im = @$create($src);
        if (!$im) {
            return move_uploaded_file($src, $dst);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($im);
            return move_uploaded_file($src, $dst);
        }
        $size = 100;
        $keepAlpha = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true);
        if ($w !== $size || $h !== $size) {
            $scale = $size / min($w, $h);
            $nw = max(1, (int)round($w * $scale));
            $nh = max(1, (int)round($h * $scale));
            $scaled = imagecreatetruecolor($nw, $nh);
            if ($keepAlpha) {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            } else {
                imagefill($scaled, 0, 0, imagecolorallocate($scaled, 255, 255, 255));
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im);

            $out = imagecreatetruecolor($size, $size);
            if ($keepAlpha) {
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            } else {
                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            }
            $sx = (int)round(($nw - $size) / 2);
            $sy = (int)round(($nh - $size) / 2);
            imagecopyresampled($out, $scaled, 0, 0, $sx, $sy, $size, $size, $size, $size);
            imagedestroy($scaled);
            $im = $out;
        }
        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($im, $dst, 88);
        } elseif ($mime === 'image/png') {
            $ok = imagepng($im, $dst);
        } elseif ($mime === 'image/gif') {
            $ok = imagegif($im, $dst);
        } elseif ($mime === 'image/webp') {
            $ok = function_exists('imagewebp') && imagewebp($im, $dst, 88);
        }
        imagedestroy($im);
        return $ok || move_uploaded_file($src, $dst);
    }
}