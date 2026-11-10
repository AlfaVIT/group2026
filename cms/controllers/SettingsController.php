<?php

class SettingsController
{
    public function index(): void
    {
        $canManage = Auth::can('manage_settings');
        $canViewStats = Auth::can('view_stats');
        $tabs = $canManage ? ['general', 'users', 'places', 'meetings'] : ['places'];
        if ($canViewStats) {
            $tabs[] = 'stats';
        }
        $tab = (string)($_GET['tab'] ?? $tabs[0]);
        if (!in_array($tab, $tabs, true)) {
            $tab = $tabs[0];
        }

        // список пользователей содержит учётные данные — только администратору
        $users = $canManage
            ? Db::fetchAll('SELECT id, address_term, name, role, is_active, phone, email, telegram, login
                            FROM users
                            ORDER BY CASE role WHEN \'Старший\' THEN 0 WHEN \'Помощник\' THEN 1 WHEN \'Казначей\' THEN 2 ELSE 3 END, name')
            : [];
        $places = Db::fetchAll(
            'SELECT p.*, u.name AS owner_name, u.address_term AS owner_term,
                    (SELECT COUNT(*) FROM place_photos ph WHERE ph.place_id = p.id) AS photos_count
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
        ], $stats));
    }

    /** Старая отдельная страница статистики — теперь вкладка настроек. */
    public function stats(): void
    {
        redirect(url('settings', ['tab' => 'stats']));
    }

    /**
     * Данные вкладки «Статистика»: входы по месяцам и последний вход каждого участника
     * (участники без входов не показываются) + журнал последних записей.
     */
    private function statsData(): array
    {
        $sort = (string)($_GET['sort'] ?? 'name');
        if (!in_array($sort, ['name', 'last'], true)) {
            $sort = 'name';
        }
        $dir = (string)($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

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

        if ($section === 'general') {
            $siteName = input('site_name');
            $theme = input('theme');
            $mailFrom = mb_strtolower(trim(input('mail_from')));
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
            Setting::set('site_name', $siteName);
            Setting::set('theme', $theme);
            Setting::set('mail_from', $mailFrom);
            set_flash('Настройки сохранены');
        }

        redirect(url('settings', ['tab' => $section]));
    }
}
