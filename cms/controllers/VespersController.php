<?php

class VespersController
{
    public function view(): void
    {
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        $meeting = $meetingId
            ? Db::fetch('SELECT id, date, topic, tag FROM meetings WHERE id = ?', [$meetingId])
            : null;

        if (!$meeting) {
            http_response_code(404);
            view('errors/404', ['title' => 'Встреча не найдена']);
            return;
        }

        $frameDate = date('Y-m-d', strtotime($meeting['date']));
        $inline = !vespers_needs_iframe($frameDate);

        $presentUsers = [];
        $slots = [];
        $readerAssignments = [];

        if ($inline) {
            $slots = vespers_reader_slots($frameDate);
            $presentUsers = $this->presentUsers($meetingId);
            foreach (Db::fetchAll(
                'SELECT passage_key, user_id FROM vespers_readers WHERE meeting_id = ?',
                [$meetingId]
            ) as $row) {
                $readerAssignments[$row['passage_key']] = (int)$row['user_id'];
            }
        }

        $readerNames = [];
        $readerAvatars = [];
        foreach ($presentUsers as $u) {
            $readerNames[(int)$u['id']] = full_name($u);
            $readerAvatars[(int)$u['id']] = avatar_url($u);
        }
        $readerMap = [];
        $readerAvatarMap = [];
        foreach ($readerAssignments as $key => $userId) {
            if (!isset($readerNames[$userId])) {
                continue;
            }
            $readerMap[$key] = $readerNames[$userId];
            $readerAvatarMap[$key] = $readerAvatars[$userId];
            if (strpos($key, 'reading:') !== 0) {
                $readerMap['reading:' . $key] = $readerNames[$userId];
                $readerAvatarMap['reading:' . $key] = $readerAvatars[$userId];
            }
        }

        view('vespers', [
            'title' => 'Вечерня братским чином',
            'meeting' => $meeting,
            'frameDate' => $frameDate,
            'vespersInline' => $inline,
            'vespersHtml' => $inline ? vespers_text_html($frameDate, $readerMap, $readerAvatarMap) : '',
            'presentUsers' => $presentUsers,
            'slots' => $slots,
            'readerAssignments' => $readerAssignments,
        ]);
    }

    public function saveReaders(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }

        $validIds = array_flip(array_map('intval', array_column($this->presentUsers($meetingId), 'id')));

        $toSave = [];
        foreach ((array)($_POST['readers'] ?? []) as $key => $userId) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            if ($userId === '' || $userId === null) {
                continue;
            }
            $uid = (int)$userId;
            if (!isset($validIds[$uid])) {
                continue;
            }
            $toSave[$key] = $uid;
        }

        Db::transaction(function () use ($meetingId, $toSave) {
            Db::delete('vespers_readers', ['meeting_id' => $meetingId]);
            foreach ($toSave as $key => $userId) {
                Db::insert('vespers_readers', [
                    'meeting_id' => $meetingId,
                    'passage_key' => $key,
                    'user_id' => $userId,
                    'updated_by' => Auth::id(),
                ]);
            }
        });

        set_flash('Чтецы сохранены');
        redirect(url('vespers', ['meeting_id' => $meetingId]));
    }

    /** Очные участники встречи: активные участники, отметившие своё очное участие (status='present'). */
    private function presentUsers(int $meetingId): array
    {
        $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
        return Db::fetchAll(
            "SELECT u.id, u.address_term, u.name, u.avatar FROM users u
             WHERE u.is_active = 1 AND u.role IN ($ph)
               AND EXISTS (
                   SELECT 1 FROM attendance a
                   WHERE a.meeting_id = ? AND a.user_id = u.id
                     AND a.status = 'present'
               )
             ORDER BY u.name",
            array_merge(ATTENDANCE_ROLES, [$meetingId])
        );
    }
}