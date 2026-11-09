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
        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($meetingId, $checked, $comments, $now) {
            foreach (MEETING_CHECKLIST as $items) {
                foreach (array_keys($items) as $key) {
                    $isChecked = !empty($checked[$key]) ? 1 : 0;
                    $comment = trim((string)($comments[$key] ?? ''));
                    if (!$isChecked && $comment === '') {
                        // пустой пункт не храним
                        Db::delete('meeting_checklist', ['meeting_id' => $meetingId, 'item' => $key]);
                        continue;
                    }
                    $exists = Db::fetch(
                        'SELECT id, checked, comment FROM meeting_checklist WHERE meeting_id = ? AND item = ?',
                        [$meetingId, $key]
                    );
                    if ($exists) {
                        // если пункт не менялся — не трогаем автора и время правки
                        if ((int)$exists['checked'] === $isChecked && (string)$exists['comment'] === $comment) {
                            continue;
                        }
                        Db::update('meeting_checklist', [
                            'checked'    => $isChecked,
                            'comment'    => $comment,
                            'updated_by' => Auth::id(),
                            'updated_at' => $now,
                        ], ['id' => $exists['id']]);
                    } else {
                        Db::insert('meeting_checklist', [
                            'meeting_id' => $meetingId,
                            'item'       => $key,
                            'checked'    => $isChecked,
                            'comment'    => $comment,
                            'updated_by' => Auth::id(),
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        });

        set_flash('Чеклист подготовки сохранён');
        redirect(url('meeting', ['id' => $meetingId]));
    }
}