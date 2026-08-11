<?php

class SettingsController
{
    public function index(): void
    {
        $tab = $_GET['tab'] ?? 'general';
        if (!in_array($tab, ['general', 'users', 'places', 'meetings'], true)) {
            $tab = 'general';
        }

        $users = Db::fetchAll('SELECT * FROM users ORDER BY CASE role WHEN \'Старший\' THEN 0 WHEN \'Помощник\' THEN 1 WHEN \'Казначей\' THEN 2 ELSE 3 END, name');
        $places = Db::fetchAll(
            'SELECT p.*, u.name AS owner_name, u.address_term AS owner_term,
                    (SELECT COUNT(*) FROM place_photos ph WHERE ph.place_id = p.id) AS photos_count
             FROM places p LEFT JOIN users u ON u.id = p.owner_id
             ORDER BY p.title'
        );
        $meetings = Db::fetchAll(
            'SELECT m.*, p.title AS place_title
             FROM meetings m LEFT JOIN places p ON p.id = m.place_id
             ORDER BY m.date DESC LIMIT 100'
        );

        view('settings', [
            'title' => 'Настройки',
            'tab' => $tab,
            'users' => $users,
            'places' => $places,
            'meetings' => $meetings,
            'themes' => theme_list(),
            'siteName' => Setting::siteName(),
            'theme' => Setting::theme(),
        ]);
    }

    public function save(): void
    {
        $section = input('section', 'general');

        if ($section === 'general') {
            $siteName = input('site_name');
            $theme = input('theme');
            if (mb_strlen($siteName) < 2) {
                set_flash('Укажите название сайта', 'danger');
                redirect(url('settings'));
            }
            if (!in_array($theme, theme_list(), true)) {
                $theme = DEFAULT_THEME;
            }
            Setting::set('site_name', $siteName);
            Setting::set('theme', $theme);
            set_flash('Настройки сохранены');
        }

        redirect(url('settings', ['tab' => $section]));
    }
}