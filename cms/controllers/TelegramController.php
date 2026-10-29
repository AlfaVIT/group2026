<?php

class TelegramController
{
    public function index(): void
    {
        $users = Db::fetchAll(
            "SELECT id, address_term, name, role, telegram, telegram_username, telegram_chat_id
             FROM users WHERE is_active = 1 ORDER BY name"
        );
        $meetings = Db::fetchAll('SELECT id, date, topic, tag FROM meetings ORDER BY date DESC LIMIT 100');

        view('telegram', [
            'title' => 'Рассылка',
            'token' => Telegram::token(),
            'botUsername' => Telegram::botUsername(),
            'configured' => Telegram::configured(),
            'users' => $users,
            'meetings' => $meetings,
        ]);
    }

    public function save(): void
    {
        $token = trim(input('telegram_bot_token'));
        $username = ltrim(trim(input('telegram_bot_username')), '@');
        if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
            set_flash('Некорректный @username бота', 'danger');
            redirect(url('telegram'));
        }
        Setting::set('telegram_bot_token', $token);
        Setting::set('telegram_bot_username', $username);
        set_flash('Настройки бота сохранены');
        redirect(url('telegram'));
    }

    public function webhook(): void
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $absolute = $scheme . '://' . $host . base_url() . '/telegram.php';

        if (input('action', 'set') === 'clear') {
            $res = Telegram::deleteWebhook();
            $ok = is_array($res) && !empty($res['ok']);
            set_flash($ok ? 'Webhook отключён' : 'Не удалось отключить webhook', $ok ? 'success' : 'danger');
        } else {
            $res = Telegram::setWebhook($absolute);
            $ok = is_array($res) && !empty($res['ok']);
            set_flash(
                $ok
                    ? 'Webhook установлен: ' . $absolute
                    : 'Не удалось установить webhook' . (is_array($res) && isset($res['description']) ? ': ' . $res['description'] : ''),
                $ok ? 'success' : 'danger'
            );
        }
        redirect(url('telegram'));
    }

    public function send(): void
    {
        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') {
            set_flash('Введите текст сообщения', 'danger');
            redirect(url('telegram'));
        }
        $parseMode = input('parse_mode');
        if (!in_array($parseMode, ['', 'HTML', 'Markdown'], true)) {
            $parseMode = '';
        }
        $scope = input('scope', 'all');
        $meetingId = (int)($_POST['meeting_id'] ?? 0);

        if ($scope === 'present' && !Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            set_flash('Выберите встречу для рассылки очным участникам', 'danger');
            redirect(url('telegram'));
        }

        $recipients = [];
        foreach (Db::fetchAll(
            'SELECT id, address_term, name, telegram_chat_id FROM users
             WHERE is_active = 1 AND telegram_chat_id IS NOT NULL AND telegram_chat_id != 0'
        ) as $u) {
            $recipients[(int)$u['telegram_chat_id']] = $u;
        }

        if ($scope === 'present') {
            $present = $this->presentUsers($meetingId);
            $ids = array_flip(array_map('intval', array_column($present, 'id')));
            $recipients = array_filter($recipients, static function ($u) use ($ids) {
                return isset($ids[(int)$u['id']]);
            });
        }

        if (!$recipients) {
            set_flash('Нет получателей: никто не подключён к боту или нет очных участников', 'warning');
            redirect(url('telegram'));
        }

        $extra = $parseMode !== '' ? ['parse_mode' => $parseMode] : [];
        $chunks = Telegram::split($text);
        $ok = 0;
        $fail = 0;
        foreach ($recipients as $chatId => $u) {
            $sent = true;
            foreach ($chunks as $part) {
                $res = Telegram::sendMessage($chatId, $part, $extra);
                if ($res === null || empty($res['ok'])) {
                    $sent = false;
                    break;
                }
            }
            if ($sent) {
                $ok++;
            } else {
                $fail++;
            }
        }

        set_flash(
            'Отправлено: ' . $ok . ', не доставлено: ' . $fail . ' из ' . count($recipients),
            $fail > 0 ? 'warning' : 'success'
        );
        redirect(url('telegram'));
    }

    /** Очные участники встречи: активные участники, отметившие своё очное участие (status='present'). */
    private function presentUsers(int $meetingId): array
    {
        $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
        return Db::fetchAll(
            "SELECT u.id FROM users u
             WHERE u.is_active = 1 AND u.role IN ($ph)
               AND EXISTS (
                   SELECT 1 FROM attendance a
                   WHERE a.meeting_id = ? AND a.user_id = u.id
                     AND a.status = 'present'
               )",
            array_merge(ATTENDANCE_ROLES, [$meetingId])
        );
    }
}