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

        Db::q(
            'INSERT INTO attendance (meeting_id, user_id, status, reason, updated_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))
             ON CONFLICT(meeting_id, user_id) DO UPDATE SET
                status = excluded.status,
                reason = excluded.reason,
                updated_at = excluded.updated_at',
            [$meetingId, Auth::id(), $status, $status === 'absent' || $status === 'online' ? $reason : '']
        );

        set_flash('Отметка сохранена');
        redirect(url('meeting', ['id' => $meetingId]));
    }
}