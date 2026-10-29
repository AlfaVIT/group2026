<?php

class ChecklistController
{
    public function save(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }

        $checked = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $comments = is_array($_POST['comments'] ?? null) ? $_POST['comments'] : [];

        Db::delete('meeting_checklist', ['meeting_id' => $meetingId]);

        $now = date('Y-m-d H:i:s');
        foreach (MEETING_CHECKLIST as $items) {
            foreach (array_keys($items) as $key) {
                $isChecked = !empty($checked[$key]) ? 1 : 0;
                $comment = trim((string)($comments[$key] ?? ''));
                if (!$isChecked && $comment === '') {
                    continue;
                }
                Db::insert('meeting_checklist', [
                    'meeting_id' => $meetingId,
                    'item' => $key,
                    'checked' => $isChecked,
                    'comment' => $comment,
                    'updated_by' => Auth::id(),
                    'updated_at' => $now,
                ]);
            }
        }

        set_flash('Чеклист подготовки сохранён');
        redirect(url('meeting', ['id' => $meetingId]));
    }
}