<?php

class AttendanceController
{
    public function save(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        $status = input('status');
        $reason = input('reason');

        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }
        if (!array_key_exists($status, ATTENDANCE_STATUSES)) {
            http_response_code(400);
            exit('Некорректный статус');
        }

        if (!Auth::can('mark_attendance')) {
            http_response_code(403);
            exit('Доступ запрещён');
        }

        // Старший и Помощник (view_attendance) могут отметить любого очного участника
        $userId = (int)Auth::id();
        if (Auth::can('view_attendance') && isset($_POST['user_id'])) {
            $target = (int)$_POST['user_id'];
            $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
            if ($target > 0 && Db::fetch(
                "SELECT id FROM users WHERE id = ? AND is_active = 1 AND role IN ($ph)",
                array_merge([$target], ATTENDANCE_ROLES)
            )) {
                $userId = $target;
            }
        }

        $reason = trim(input('reason'));

        // Куда вернуть: для AJAX с других страниц (главная/календарь) — обратно на них,
        // чтобы не уходить со страницы; иначе — на страницу встречи.
        $back = url('meeting', ['id' => $meetingId]);
        if (is_ajax() && !empty($_SERVER['HTTP_REFERER'])) {
            $refHost = (string)parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $selfHost = (string)($_SERVER['HTTP_HOST'] ?? '');
            if ($refHost !== '' && strcasecmp($refHost, $selfHost) === 0) {
                $back = $_SERVER['HTTP_REFERER'];
            }
        }

        if ($status === 'absent' && $reason === '') {
            set_flash('Для отметки об отсутствии укажите причину', 'danger');
            redirect($back);
        }

        Db::q(
            'INSERT INTO attendance (meeting_id, user_id, status, reason, updated_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))
             ON CONFLICT(meeting_id, user_id) DO UPDATE SET
                status = excluded.status,
                reason = excluded.reason,
                updated_at = excluded.updated_at',
            [$meetingId, $userId, $status, $status === 'absent' || $status === 'online' ? $reason : '']
        );

        set_flash($status === 'present'
            ? 'Отметка сохранена: буду очно'
            : 'Отметка сохранена');
        redirect($back);
    }
}