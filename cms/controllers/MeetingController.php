<?php

class MeetingController
{
    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        $meeting = Db::fetch(
            'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                    p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom,
                    p.geo_link, p.note AS place_note,
                    u.name AS owner_name, u.address_term AS owner_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE m.id = ?',
            [$id]
        );

        if (!$meeting) {
            http_response_code(404);
            view('errors/404', ['title' => 'Встреча не найдена']);
            return;
        }

        $roles = Db::fetchAll(
            'SELECT mr.meeting_id, mr.role, mr.user_id, u.name, u.address_term
             FROM meeting_roles mr JOIN users u ON u.id = mr.user_id
             WHERE mr.meeting_id = ? ORDER BY u.name',
            [$id]
        );

        $attendance = [];
        if (Auth::can('view_attendance')) {
            $attendance = Db::fetchAll(
                'SELECT a.*, u.name, u.address_term
                 FROM attendance a JOIN users u ON u.id = a.user_id
                 WHERE a.meeting_id = ? ORDER BY a.status, u.name',
                [$id]
            );
        }

        $expenses = [];
        $expensesTotal = 0.0;
        if (Auth::can('view_expenses')) {
            $expenses = Db::fetchAll(
                'SELECT e.*, u.name, u.address_term
                 FROM expenses e JOIN users u ON u.id = e.user_id
                 WHERE e.meeting_id = ? ORDER BY e.created_at DESC',
                [$id]
            );
            $expensesTotal = (float)(Db::value('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE meeting_id = ?', [$id]) ?? 0);
        }

        $activeUsers = [];
        if (Auth::can('view_attendance') || Auth::can('assign_roles')) {
            $activeUsers = Db::fetchAll(
                'SELECT id, address_term, name FROM users WHERE is_active = 1 ORDER BY name'
            );
        }

        view('meeting', [
            'title' => 'Встреча ' . rdate($meeting['date']),
            'meeting' => $meeting,
            'roles' => $roles,
            'attendance' => $attendance,
            'expenses' => $expenses,
            'expensesTotal' => $expensesTotal,
            'activeUsers' => $activeUsers,
            'myAttendance' => Db::fetch('SELECT * FROM attendance WHERE meeting_id = ? AND user_id = ?', [$id, Auth::id()]),
        ]);
    }

    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $meeting = $id
            ? Db::fetch('SELECT * FROM meetings WHERE id = ?', [$id])
            : null;

        if ($id && !$meeting) {
            http_response_code(404);
            view('errors/404', ['title' => 'Встреча не найдена']);
            return;
        }

        $places = Db::fetchAll('SELECT * FROM places ORDER BY title');
        $prev = $_SESSION['meeting_form'] ?? null;
        unset($_SESSION['meeting_form']);

        $data = $prev ?? $meeting ?? [
            'date' => date('Y-m-d\TH:i', strtotime('+1 week')),
            'place_id' => '',
            'topic' => '',
            'materials' => '[]',
            'guests' => '',
            'participants_count' => '',
            'stream_url' => '',
        ];

        view('meeting_edit', [
            'title' => $meeting ? 'Редактирование встречи' : 'Новая встреча',
            'meeting' => $meeting,
            'data' => $data,
            'places' => $places,
            'materials' => decode_materials($data['materials']),
        ]);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $placeId = (int)input('place_id');
        $date = input('date');
        $topic = input('topic');
        $guests = input('guests');
        $streamUrl = input('stream_url');
        $participants = (int)input('participants_count');

        $errors = [];
        if (!strtotime($date)) {
            $errors[] = 'Укажите корректную дату проведения';
        }
        if ($placeId && !Db::fetch('SELECT id FROM places WHERE id = ?', [$placeId])) {
            $errors[] = 'Указанное место не найдено';
        }

        $materials = [];
        $titles = post('mat_title') ?: [];
        $types = post('mat_type') ?: [];
        $texts = post('mat_text') ?: [];
        $urls = post('mat_url') ?: [];
        foreach ($titles as $i => $mt) {
            $type = $types[$i] ?? 'text';
            $title = trim((string)$mt);
            if ($type === 'link') {
                $url = trim((string)($urls[$i] ?? ''));
                if ($url === '') {
                    continue;
                }
                if (!preg_match('~^https?://~i', $url)) {
                    $url = 'https://' . $url;
                }
                $materials[] = ['type' => 'link', 'title' => $title !== '' ? $title : $url, 'url' => $url];
            } else {
                $text = trim((string)($texts[$i] ?? ''));
                if ($text === '') {
                    continue;
                }
                $materials[] = ['type' => 'text', 'title' => $title, 'text' => $text];
            }
        }

        if ($errors) {
            $_SESSION['meeting_form'] = [
                'date' => $date,
                'place_id' => $placeId,
                'topic' => $topic,
                'materials' => json_encode($materials, JSON_UNESCAPED_UNICODE),
                'guests' => $guests,
                'participants_count' => $participants,
                'stream_url' => $streamUrl,
            ];
            set_flash(implode('<br>', $errors), 'danger');
            redirect(url($id ? 'meeting_edit' : 'meeting_edit', ['id' => $id ?: null]));
        }

        $data = [
            'date' => $date,
            'place_id' => $placeId ?: null,
            'topic' => $topic,
            'materials' => json_encode($materials, JSON_UNESCAPED_UNICODE),
            'guests' => $guests,
            'participants_count' => $participants,
            'stream_url' => $streamUrl,
        ];

        if ($id) {
            if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$id])) {
                http_response_code(404);
                exit('Встреча не найдена');
            }
            Db::update('meetings', $data, ['id' => $id]);
            $meetingId = $id;
        } else {
            $meetingId = Db::insert('meetings', $data);
        }

        set_flash('Встреча сохранена');
        redirect(url('meeting', ['id' => $meetingId]));
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$id])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }
        Db::delete('meetings', ['id' => $id]);
        set_flash('Встреча удалена', 'info');
        redirect(url('home'));
    }
}