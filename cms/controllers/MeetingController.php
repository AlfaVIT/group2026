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
                    u.name AS owner_name, u.address_term AS owner_term,
                    su.name AS senior_name, su.address_term AS senior_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users u ON u.id = p.owner_id
             LEFT JOIN users su ON su.id = m.senior_user_id
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
            $rows = Db::fetchAll(
                'SELECT a.*, u.name, u.address_term
                 FROM attendance a JOIN users u ON u.id = a.user_id
                 WHERE a.meeting_id = ?',
                [$id]
            );
            $byUser = [];
            foreach ($rows as $r) {
                $byUser[$r['user_id']] = $r;
            }
            $order = ['present' => 0, 'online' => 1, 'absent' => 2, '' => 3];
            foreach (attendance_eligible() as $u) {
                $row = $byUser[$u['id']] ?? null;
                $status = $row ? $row['status'] : '';
                $attendance[] = [
                    'user_id'     => (int)$u['id'],
                    'name'        => $u['name'],
                    'address_term' => $u['address_term'],
                    'role'        => $u['role'],
                    'status'      => $status,
                    'reason'      => $row ? trim((string)$row['reason']) : '',
                    'updated_at'  => $row ? $row['updated_at'] : '',
                    '_order'      => $order[$status],
                ];
            }
            usort($attendance, function ($a, $b) {
                return [$a['_order'], mb_strtolower($a['name'])] <=> [$b['_order'], mb_strtolower($b['name'])];
            });
        }

        $expenses = [];
        $expensesTotal = 0.0;
        $myExpensesTotal = 0.0;
        if (Auth::can('view_expenses') || Auth::can('add_expense')) {
            $expenses = Db::fetchAll(
                'SELECT e.*, u.name, u.address_term
                 FROM expenses e JOIN users u ON u.id = e.user_id
                 WHERE e.meeting_id = ? ORDER BY e.created_at DESC',
                [$id]
            );
            $expensesTotal = (float)(Db::value('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE meeting_id = ?', [$id]) ?? 0);
            $myExpensesTotal = (float)(Db::value('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE meeting_id = ? AND user_id = ?', [$id, Auth::id()]) ?? 0);
            if (!Auth::can('view_expenses')) {
                $expenses = array_values(array_filter($expenses, fn($e) => (int)$e['user_id'] === Auth::id()));
                $expensesTotal = $myExpensesTotal;
            }
        }

        $activeUsers = [];
        if (Auth::can('view_attendance') || Auth::can('assign_roles')) {
            $activeUsers = Db::fetchAll(
                'SELECT id, address_term, name FROM users WHERE is_active = 1 ORDER BY name'
            );
        }

        $my = Db::fetch('SELECT * FROM attendance WHERE meeting_id = ? AND user_id = ?', [$id, Auth::id()]);
        if (!$my) {
            $my = ['status' => '', 'reason' => ''];
        }

        $checklist = [];
        foreach (Db::fetchAll('SELECT item, checked, comment FROM meeting_checklist WHERE meeting_id = ?', [$id]) as $cl) {
            $checklist[$cl['item']] = ['checked' => (int)$cl['checked'], 'comment' => (string)$cl['comment']];
        }

        $presentCount = present_active_count($id);
        $guestNames = meeting_guest_names((string)$meeting['guests']);
        $guestCount = count_meeting_guests((string)$meeting['guests']);
        $participantsCount = $presentCount + $guestCount;

        view('meeting', [
            'title' => 'Встреча ' . rdate($meeting['date']),
            'meeting' => $meeting,
            'roles' => $roles,
            'attendance' => $attendance,
            'expenses' => $expenses,
            'expensesTotal' => $expensesTotal,
            'activeUsers' => $activeUsers,
            'myAttendance' => $my,
            'presentCount' => $presentCount,
            'participantsCount' => $participantsCount,
            'guestNames' => $guestNames,
            'checklist' => $checklist,
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
        $tags = Db::fetchAll("SELECT DISTINCT tag FROM meetings WHERE tag != '' ORDER BY tag");
        $usedTypes = array_column(Db::fetchAll("SELECT DISTINCT meeting_type FROM meetings WHERE meeting_type != '' ORDER BY meeting_type"), 'meeting_type');
        $meetingTypes = array_values(array_unique(array_merge(MEETING_TYPE_PRESETS, $usedTypes)));
        $guestUsers = Db::fetchAll("SELECT id, address_term, name FROM users WHERE role = 'Гость' ORDER BY name");
        $prev = $_SESSION['meeting_form'] ?? null;
        unset($_SESSION['meeting_form']);

        $data = $prev ?? $meeting ?? [
            'date' => date('Y-m-d\TH:i', strtotime('+1 week')),
            'place_id' => '',
            'topic' => '',
            'tag' => '',
            'meeting_type' => '',
            'materials' => '[]',
            'guests' => '',
            'stream_url' => '',
        ];

        $guestIds = array_values(array_filter(
            array_map('trim', explode(',', (string)($data['guests'] ?? ''))),
            'ctype_digit'
        ));

        $seniorCandidates = [];
        $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
        if ($id) {
            $presentMembers = Db::fetchAll(
                "SELECT id, address_term, name FROM users
                 WHERE is_active = 1 AND role IN ($ph)
                   AND EXISTS (SELECT 1 FROM attendance a
                               WHERE a.meeting_id = ? AND a.user_id = users.id
                                 AND a.status = 'present') ORDER BY name",
                array_merge(ATTENDANCE_ROLES, [$id])
            );
        } else {
            $presentMembers = Db::fetchAll(
                "SELECT id, address_term, name FROM users
                 WHERE is_active = 1 AND role IN ($ph) ORDER BY name",
                ATTENDANCE_ROLES
            );
        }
        foreach (array_merge($presentMembers, $guestUsers) as $cu) {
            $seniorCandidates[(int)$cu['id']] = $cu;
        }
        $curSenior = (int)($data['senior_user_id'] ?? 0);
        if ($curSenior && !isset($seniorCandidates[$curSenior])) {
            $su = Db::fetch('SELECT id, address_term, name FROM users WHERE id = ?', [$curSenior]);
            if ($su) {
                $seniorCandidates[(int)$su['id']] = $su;
            }
        }
        $seniorCandidates = array_values($seniorCandidates);
        usort($seniorCandidates, function ($a, $b) {
            return mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
        });

        view('meeting_edit', [
            'title' => $meeting ? 'Редактирование встречи' : 'Новая встреча',
            'meeting' => $meeting,
            'data' => $data,
            'places' => $places,
            'tags' => $tags,
            'meetingTypes' => $meetingTypes,
            'guestUsers' => $guestUsers,
            'guestIds' => $guestIds,
            'materials' => decode_materials($data['materials']),
            'seniorCandidates' => $seniorCandidates,
        ]);
    }

    public function save(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $placeId = (int)input('place_id');
        $date = input('date');
        $topic = input('topic');
        $tag = trim(input('tag'));
        $meetingType = trim(input('meeting_type'));
        $streamUrl = input('stream_url');
        $seniorUserId = (int)post('senior_user_id', 0);

        $errors = [];
        $guestIds = array_values(array_unique(array_map('intval', (array)post('guests', []))));
        if ($guestIds) {
            $ph = implode(',', array_fill(0, count($guestIds), '?'));
            $validIds = array_flip(array_map(
                'intval',
                array_column(Db::fetchAll("SELECT id FROM users WHERE role = 'Гость' AND id IN ($ph)", $guestIds), 'id')
            ));
            foreach ($guestIds as $gid) {
                if (!isset($validIds[$gid])) {
                    $errors[] = 'Указан некорректный гость';
                    break;
                }
            }
        }
        $guests = implode(',', $guestIds);

        if (!strtotime($date)) {
            $errors[] = 'Укажите корректную дату проведения';
        }
        if (mb_strlen($tag) > 50) {
            $errors[] = 'Метка встречи не длиннее 50 символов';
        }
        if ($placeId && !Db::fetch('SELECT id FROM places WHERE id = ?', [$placeId])) {
            $errors[] = 'Указанное место не найдено';
        }
        if ($seniorUserId && !Db::fetch('SELECT id FROM users WHERE id = ? AND is_active = 1', [$seniorUserId])) {
            $errors[] = 'Указан некорректный старший на встрече';
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
                $materials[] = ['type' => 'link', 'title' => $title, 'url' => $url];
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
                'tag' => $tag,
                'meeting_type' => $meetingType,
                'materials' => json_encode($materials, JSON_UNESCAPED_UNICODE),
                'guests' => $guests,
                'stream_url' => $streamUrl,
                'senior_user_id' => $seniorUserId ?: null,
            ];
            set_flash(implode('<br>', $errors), 'danger');
            redirect(url($id ? 'meeting_edit' : 'meeting_edit', ['id' => $id ?: null]));
        }

        $data = [
            'date' => $date,
            'place_id' => $placeId ?: null,
            'topic' => $topic,
            'tag' => $tag,
            'meeting_type' => $meetingType,
            'materials' => json_encode($materials, JSON_UNESCAPED_UNICODE),
            'guests' => $guests,
            'participants_count' => 0,
            'senior_user_id' => $seniorUserId ?: null,
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

    /** Отдаёт встречу файлом .ics — добавление в календарь (на мобильных — в календарь устройства). */
    public function ics(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $meeting = Db::fetch(
            'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                    p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom, p.geo_link,
                    su.name AS senior_name, su.address_term AS senior_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users su ON su.id = m.senior_user_id
             WHERE m.id = ?',
            [$id]
        );
        if (!$meeting) {
            http_response_code(404);
            exit('Встреча не найдена');
        }

        $ics = build_meeting_ics($meeting);
        $ts = strtotime((string)$meeting['date']);
        // iOS открывает .ics в календаре напрямую (inline), остальные — скачивают файл
        $disposition = is_ios_client() ? 'inline' : 'attachment';
        header('Content-Type: text/calendar; charset=UTF-8');
        header('Content-Disposition: ' . $disposition . '; filename="meeting-' . date('Ymd', $ts ?: time()) . '.ics"');
        header('Cache-Control: no-store');
        echo $ics;
        exit;
    }
}