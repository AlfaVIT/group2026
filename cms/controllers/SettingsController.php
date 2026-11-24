<?php

class SettingsController
{
    public function index(): void
    {
        $canManage = Auth::can('manage_settings');
        $canViewStats = Auth::can('view_stats');
        $tabs = $canManage ? ['general', 'users', 'places', 'meetings', 'maintenance'] : ['places'];
        if ($canViewStats) {
            $tabs[] = 'stats';
        }
        $tab = get_str('tab', $tabs[0]);
        if (!in_array($tab, $tabs, true)) {
            $tab = $tabs[0];
        }

        // список пользователей содержит учётные данные — только администратору
        $users = $canManage
            ? self::sortUsersByRole(Db::fetchAll('SELECT id, address_term, name, role, is_active, phone, hide_phone,
                                   birthday, email, telegram, telegram_chat_id, avatar, login
                            FROM users'))
            : [];
        $places = Db::fetchAll(
            'SELECT p.*, u.name AS owner_name, u.address_term AS owner_term, u.avatar AS owner_avatar,
                    (SELECT COUNT(*) FROM place_photos ph WHERE ph.place_id = p.id) AS photos_count,
                    (SELECT ph.file_path FROM place_photos ph WHERE ph.place_id = p.id
                     ORDER BY ph.sort, ph.id LIMIT 1) AS first_photo
             FROM places p LEFT JOIN users u ON u.id = p.owner_id
             ORDER BY p.title'
        );
        $meetings = $canManage
            ? Db::fetchAll(
                'SELECT m.*, p.title AS place_title
                 FROM meetings m LEFT JOIN places p ON p.id = m.place_id
                 ORDER BY m.date DESC LIMIT 100'
            )
            : [];

        $meetingParticipants = [];
        if ($meetings) {
            $ids = implode(',', array_map('intval', array_column($meetings, 'id')));
            $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
            $marked = [];
            foreach (Db::fetchAll(
                "SELECT a.meeting_id, COUNT(*) AS cnt
                 FROM attendance a JOIN users u ON u.id = a.user_id
                 WHERE a.meeting_id IN ($ids) AND a.status = 'present' AND u.role IN ($ph)
                 GROUP BY a.meeting_id",
                ATTENDANCE_ROLES
            ) as $row) {
                $marked[(int)$row['meeting_id']] = (int)$row['cnt'];
            }
            foreach ($meetings as $m) {
                $meetingParticipants[$m['id']] = ($marked[$m['id']] ?? 0)
                    + count_meeting_guests((string)$m['guests']);
            }
        }

        $stats = $tab === 'stats' ? $this->statsData() : [];

        // вкладка «Обслуживание»: расписание бекапов, токен запуска и уведомления
        $backup = $canManage ? backup_schedule() : [];
        $backupNext = $backup ? backup_next_slot($backup) : null;
        $cronUrl = $canManage ? url('backup_cron', ['token' => Setting::cronToken()]) : '';
        $cronCommand = 'php ' . BASE_PATH . '/cron/backup.php';

        view('settings', array_merge([
            'title' => 'Настройки',
            'tab' => $tab,
            'canManage' => $canManage,
            'canViewStats' => $canViewStats,
            'users' => $users,
            'places' => $places,
            'meetings' => $meetings,
            'meetingParticipants' => $meetingParticipants,
            'themes' => theme_list(),
            'siteName' => Setting::siteName(),
            'theme' => Setting::theme(),
            'mailFrom' => mail_from(),
            'adminEmail' => Setting::adminEmail(),
            'streamUrl' => Setting::streamUrl(),
            'financeEnabled' => Setting::financeEnabled(),
            'backup' => $backup,
            'backupNext' => $backupNext,
            'cronUrl' => $cronUrl,
            'cronCommand' => $cronCommand,
            'crypto' => ($canManage && $tab === 'maintenance') ? crypto_status() : [],
            'notifyBruteforce' => (string)Setting::get('notify_bruteforce', '1') === '1',
            'notifyAdminLogin' => (string)Setting::get('notify_admin_login', '1') === '1',
            'smtp' => [
                'enabled' => (string)Setting::get('smtp_enabled', '0') === '1',
                'host'    => (string)Setting::get('smtp_host', 'smtp.mail.ru'),
                'port'    => (string)Setting::get('smtp_port', '465'),
                'secure'  => (string)Setting::get('smtp_secure', 'ssl'),
                'user'    => (string)Setting::get('smtp_user', ''),
                'has_pass' => (string)Setting::get('smtp_pass', '') !== '',
            ],
            'mailLastError' => (string)Setting::get('mail_last_error', ''),
        ], $stats));
    }

    /** Старая отдельная страница статистики — теперь вкладка настроек. */
    public function stats(): void
    {
        redirect(url('settings', ['tab' => 'stats']));
    }

    /** Сортировка списка сотрудников: сначала по роли, внутри — по имени. */
    private static function sortUsersByRole(array $users): array
    {
        $order = ['Старший' => 0, 'Помощник' => 1, 'Казначей' => 2];
        usort($users, static function (array $a, array $b) use ($order): int {
            $ra = $order[(string)($a['role'] ?? '')] ?? 3;
            $rb = $order[(string)($b['role'] ?? '')] ?? 3;
            return $ra <=> $rb ?: strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $users;
    }

    /**
     * Данные вкладки «Статистика»: входы по месяцам и последний вход каждого участника
     * (участники без входов не показываются) + журнал последних записей.
     */
    private function statsData(): array
    {
        $sort = get_str('sort', 'name');
        if (!in_array($sort, ['name', 'last'], true)) {
            $sort = 'name';
        }
        $dir = get_str('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $visits = Db::fetchAll(
            'SELECT v.*, u.address_term, u.name
             FROM visits v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.id DESC
             LIMIT 50'
        );

        $byUser = [];
        $monthKeys = [];
        foreach (Db::fetchAll(
            "SELECT user_id, substr(created_at, 1, 7) AS ym, COUNT(*) AS cnt, MAX(created_at) AS last_at
             FROM visits GROUP BY user_id, substr(created_at, 1, 7)"
        ) as $row) {
            $uid = (int)$row['user_id'];
            $ym = (string)$row['ym'];
            $byUser[$uid]['months'][$ym] = (int)$row['cnt'];
            $byUser[$uid]['total'] = ($byUser[$uid]['total'] ?? 0) + (int)$row['cnt'];
            $last = (string)$row['last_at'];
            if (!isset($byUser[$uid]['last']) || $last > $byUser[$uid]['last']) {
                $byUser[$uid]['last'] = $last;
            }
            $monthKeys[$ym] = true;
        }

        $months = $this->monthsList(array_keys($monthKeys));

        // только те, у кого есть входы
        $users = array_values(array_filter(
            Db::fetchAll('SELECT id, address_term, name, role, is_active FROM users'),
            static fn(array $u): bool => isset($byUser[(int)$u['id']])
        ));
        usort($users, function (array $a, array $b) use ($sort, $dir, $byUser): int {
            if ($sort === 'last') {
                $cmp = strcmp(
                    (string)($byUser[(int)$a['id']]['last'] ?? ''),
                    (string)($byUser[(int)$b['id']]['last'] ?? '')
                );
                if ($cmp === 0) {
                    $cmp = mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
                }
            } else {
                $cmp = mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
            }
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return [
            'statsVisits' => $visits,
            'statsUsers' => $users,
            'statsByUser' => $byUser,
            'months' => $months,
            'monthLabels' => $this->monthLabels($months),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /** Непрерывный список месяцев (не больше 12) от первого входа до текущего. */
    private function monthsList(array $keys): array
    {
        $current = date('Y-m');
        if (!$keys) {
            return [$current];
        }
        sort($keys, SORT_STRING);
        $from = $keys[0] < $current ? $keys[0] : $current;
        $list = [];
        $ts = strtotime($from . '-01');
        $end = strtotime($current . '-01');
        while ($ts !== false && $ts <= $end) {
            $list[] = date('Y-m', $ts);
            $ts = strtotime('+1 month', $ts);
        }
        return count($list) > 12 ? array_slice($list, -12) : $list;
    }

    /** Подписи месяцев вида «ноя 26». */
    private function monthLabels(array $months): array
    {
        $names = [1 => 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        $out = [];
        foreach ($months as $m) {
            $ts = strtotime($m . '-01');
            if ($ts === false) {
                $out[$m] = $m;
                continue;
            }
            $out[$m] = $names[(int)date('n', $ts)] . ' ' . date('y', $ts);
        }
        return $out;
    }

    public function save(): void
    {
        $section = input('section', 'general');

        if ($section === 'maintenance') {
            $this->saveMaintenance();
            redirect(url('settings', ['tab' => 'maintenance']));
        }

        if ($section === 'general') {
            $siteName = input('site_name');
            $theme = input('theme');
            $mailFrom = mb_strtolower(trim(input('mail_from')));
            $adminEmail = mb_strtolower(trim(input('admin_email')));
            $streamUrl = trim(input('stream_url'));
            // невыбранный чекбокс браузер не отправляет — это и есть «выключено»
            $financeEnabled = input('finance_enabled') === '1';
            if (mb_strlen($siteName) < 2) {
                set_flash('Укажите название сайта', 'danger');
                redirect(url('settings'));
            }
            if (!in_array($theme, theme_list(), true)) {
                $theme = DEFAULT_THEME;
            }
            if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
                set_flash('Укажите корректный email отправителя', 'danger');
                redirect(url('settings'));
            }
            // пустое значение допустимо: уведомления просто не будут отправляться
            if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                set_flash('Укажите корректный email администратора', 'danger');
                redirect(url('settings'));
            }
            if ($streamUrl !== '' && (!filter_var($streamUrl, FILTER_VALIDATE_URL)
                    || !preg_match('#^https?://#i', $streamUrl))) {
                set_flash('Ссылка на трансляцию должна начинаться с http:// или https://', 'danger');
                redirect(url('settings'));
            }
            Setting::set('site_name', $siteName);
            Setting::set('theme', $theme);
            Setting::set('mail_from', $mailFrom);
            Setting::set('admin_email', $adminEmail);
            Setting::set('stream_url', $streamUrl);
            Setting::set('finance_enabled', $financeEnabled ? '1' : '0');
            set_flash('Настройки сохранены');
        }

        redirect(url('settings', ['tab' => $section]));
    }

    /** Вкладка «Обслуживание»: расписание бекапов, уведомления, ручные действия. */
    private function saveMaintenance(): void
    {
        $action = input('action', 'save');

        if ($action === 'backup_now') {
            $res = backup_send(true);
            set_flash($res['message'], $res['ok'] ? 'success' : 'danger');
            return;
        }

        if ($action === 'rotate_token') {
            Setting::set('cron_token', bin2hex(random_bytes(24)));
            set_flash('Токен обновлён: старый URL больше не работает', 'info');
            return;
        }

        if ($action === 'crypto_sweep') {
            $res = crypto_sweep();
            if (!$res['ok']) {
                set_flash((string)$res['error'], 'danger');
                return;
            }
            $n = (int)$res['encrypted'];
            set_flash(
                $res['done']
                    ? 'Готово: зашифровано записей — ' . $n . '. Открытых значений больше нет.'
                    : 'Зашифровано записей — ' . $n . '. Осталось ещё: нажмите кнопку ещё раз.',
                'success'
            );
            return;
        }

        if ($action === 'mail_test') {
            $to = Setting::adminEmail();
            if ($to === '') {
                set_flash('Сначала укажите «Почту администратора» на вкладке «Общее»', 'danger');
                return;
            }
            $ok = send_email($to, 'Тестовое письмо — ' . Setting::siteName(), '<p>Это тестовое письмо с сайта «'
                . e(Setting::siteName()) . '».</p><p>Если вы его получили, отправка почты настроена правильно.</p>');
            $err = (string)Setting::get('mail_last_error', '');
            set_flash(
                $ok
                    ? 'Тестовое письмо отправлено на ' . $to
                    : 'Не удалось отправить: ' . ($err !== '' ? $err : 'неизвестная ошибка'),
                $ok ? 'success' : 'danger'
            );
            return;
        }

        $enabled = input('backup_enabled') === '1';
        $freq = input('backup_freq', 'daily');
        if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $freq = 'daily';
        }
        $time = input('backup_time', '03:00');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '03:00';
        }
        $weekday = (int)input('backup_weekday', '1');
        if ($weekday < 1 || $weekday > 7) {
            $weekday = 1;
        }
        $day = (int)input('backup_day', '1');
        if ($day < 1 || $day > 28) {
            $day = 1;
        }

        Setting::set('backup_enabled', $enabled ? '1' : '0');
        Setting::set('backup_freq', $freq);
        Setting::set('backup_time', $time);
        Setting::set('backup_weekday', (string)$weekday);
        Setting::set('backup_day', (string)$day);
        Setting::set('notify_bruteforce', input('notify_bruteforce') === '1' ? '1' : '0');
        Setting::set('notify_admin_login', input('notify_admin_login') === '1' ? '1' : '0');

        // SMTP: From при включении = логин ящика, иначе провайдер отклонит письмо
        $smtpEnabled = input('smtp_enabled') === '1';
        $smtpHost = trim(input('smtp_host'));
        $smtpPort = (int)input('smtp_port', '465');
        if ($smtpPort < 1 || $smtpPort > 65535) {
            $smtpPort = 465;
        }
        $smtpSecure = input('smtp_secure', 'ssl');
        if (!in_array($smtpSecure, ['ssl', 'tls', ''], true)) {
            $smtpSecure = 'ssl';
        }
        $smtpUser = trim(input('smtp_user'));
        if ($smtpUser !== '' && !filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
            set_flash('Укажите корректный логин SMTP (адрес почты)', 'danger');
            return;
        }
        // пароль из поля не тримим: пробелы могут быть значимыми
        $smtpPass = (string)($_POST['smtp_pass'] ?? '');
        if ($smtpPass !== '') {
            Setting::set('smtp_pass', $smtpPass);
        }
        Setting::set('smtp_enabled', $smtpEnabled ? '1' : '0');
        Setting::set('smtp_host', $smtpHost);
        Setting::set('smtp_port', (string)$smtpPort);
        Setting::set('smtp_secure', $smtpSecure);
        Setting::set('smtp_user', $smtpUser);

        if ($smtpEnabled && ($smtpHost === '' || $smtpUser === '' || (string)Setting::get('smtp_pass', '') === '')) {
            set_flash('SMTP включён, но не заполнены сервер, логин или пароль — письма не отправятся', 'warning');
            return;
        }

        if ($enabled && Setting::adminEmail() === '') {
            set_flash('Расписание сохранено, но бекапы не будут отправляться: заполните «Почту администратора» на вкладке «Общее»', 'warning');
            return;
        }
        set_flash('Настройки обслуживания сохранены');
    }
}
