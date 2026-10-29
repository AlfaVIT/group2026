<?php

class HomeController
{
    public function index(): void
    {
        $data = $this->meetingsData();

        $sel = null;
        if (isset($_GET['sel']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sel'])) {
            $sel = $_GET['sel'];
        }
        $mParam = null;
        if (isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m'])) {
            $mParam = $_GET['m'];
        }

        $selected = null;
        if ($sel) {
            foreach (array_merge($data['upcoming'], $data['past']) as $meeting) {
                if (substr($meeting['date'], 0, 10) === $sel) {
                    $selected = $meeting;
                    break;
                }
            }
        } elseif (!$mParam) {
            $selected = $data['upcoming'][0] ?? null;
        } else {
            foreach ($data['upcoming'] as $meeting) {
                if (substr($meeting['date'], 0, 7) === $mParam) {
                    $selected = $meeting;
                    break;
                }
            }
        }

        $stripMonth = $mParam
            ?? ($sel ? substr($sel, 0, 7) : ($selected ? substr($selected['date'], 0, 7) : null));

        $monthNames = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        $todayN = (int)date('n');
        $todayJ = (int)date('j');
        $curY = (int)date('Y');
        $bdYears = [];
        for ($i = 0; $i < 12; $i++) {
            $n = ($todayN + $i - 1) % 12 + 1;
            $bdYears[$n] = $n >= $todayN ? $curY : $curY + 1;
        }
        $birthdays = [];
        foreach (Db::fetchAll("SELECT name, avatar, birthday FROM users WHERE is_active = 1 AND birthday != ''") as $u) {
            $b = $u['birthday'];
            $noYear = !birthday_has_year($b);
            $ts = strtotime($noYear ? '2000-' . substr($b, 5) : $b);
            if ($ts === false) {
                continue;
            }
            $m = (int)date('n', $ts);
            $day = (int)date('j', $ts);
            $occY = $bdYears[$m];
            $turns = $noYear ? null : ($occY - (int)date('Y', $ts));
            if ($turns !== null && $turns < 0) {
                continue;
            }
            $status = 'upcoming';
            if ($m === $todayN && $day === $todayJ) {
                $status = 'today';
            } elseif ($m === $todayN && $day < $todayJ) {
                $status = 'passed';
            }
            $birthdays[$m][] = [
                'name'      => $u['name'],
                'avatarUrl' => avatar_url($u),
                'day'       => $day,
                'month'     => $monthNames[$m],
                'turns'     => $turns,
                'status'    => $status,
                'year'      => $occY,
            ];
        }
        foreach ($birthdays as &$list) {
            usort($list, fn($a, $b) => $a['day'] <=> $b['day']);
        }
        unset($list);

        $contacts = Db::fetchAll(
            'SELECT name, address_term, avatar, phone, hide_phone, email, telegram
             FROM users WHERE is_active = 1 AND hide_phone = 0 AND COALESCE(phone, \'\') != \'\'
             ORDER BY name'
        );

        view('home', [
            'title' => Setting::siteName(),
            'strip' => $this->stripData($stripMonth, $sel ?? ($selected ? substr($selected['date'], 0, 10) : null)),
            'selected' => $selected,
            'upcoming' => $data['upcoming'],
            'past' => $data['past'],
            'attendanceSummary' => $data['attendanceSummary'],
            'roleAssignment' => $data['roleAssignment'],
            'myAttendance' => $data['myAttendance'],
            'birthdays' => $birthdays,
            'bdYears' => $bdYears,
            'contacts' => $contacts,
        ]);
    }

    public function calendar(): void
    {
        $sel = null;
        if (isset($_GET['sel']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sel'])) {
            $sel = $_GET['sel'];
        }
        $mParam = null;
        if (isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m'])) {
            $mParam = $_GET['m'];
        }

        $nearest = null;
        if (!$mParam && !$sel) {
            $nearest = Db::fetch(
                'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                        p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom,
                        p.geo_link, p.note AS place_note,
                        u.name AS owner_name, u.address_term AS owner_term
                 FROM meetings m
                 LEFT JOIN places p ON p.id = m.place_id
                 LEFT JOIN users u ON u.id = p.owner_id
                 WHERE m.date >= :today
                 ORDER BY m.date ASC, m.id DESC
                 LIMIT 1',
                [':today' => today()]
            ) ?: null;
        }

        $stripMonth = $mParam
            ?? ($sel ? substr($sel, 0, 7) : ($nearest ? substr($nearest['date'], 0, 7) : null));
        $strip = $this->stripData($stripMonth, $sel);

        $from = $strip['year'] . '-' . str_pad((string)$strip['month'], 2, '0', STR_PAD_LEFT) . '-01';
        $to = $strip['year'] . '-' . str_pad((string)$strip['month'], 2, '0', STR_PAD_LEFT) . '-' . $strip['days'];

        $monthMeetings = Db::fetchAll(
            'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                    p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom,
                    p.geo_link, p.note AS place_note,
                    u.name AS owner_name, u.address_term AS owner_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE m.date >= :from AND m.date <= :to
             ORDER BY m.date ASC, m.id DESC',
            [':from' => $from, ':to' => $to . 'T23:59']
        );

        $summary = $this->withSummary(array_merge($monthMeetings, $nearest ? [$nearest] : []));
        $today = today();
        $monthUpcoming = [];
        $monthPast = [];
        foreach ($monthMeetings as $m) {
            if ($m['date'] >= $today) {
                $monthUpcoming[] = $m;
            } else {
                $monthPast[] = $m;
            }
        }

        $selected = null;
        if ($sel) {
            foreach ($monthMeetings as $meeting) {
                if (substr($meeting['date'], 0, 10) === $sel) {
                    $selected = $meeting;
                    break;
                }
            }
        } elseif ($mParam) {
            $selected = $monthUpcoming[0] ?? null;
        } else {
            $selected = $nearest;
        }

        $strip['selected'] = $sel ?? ($selected ? substr($selected['date'], 0, 10) : null);

        view('calendar', [
            'title' => 'Календарь встреч',
            'strip' => $strip,
            'selected' => $selected,
            'monthUpcoming' => $monthUpcoming,
            'monthPast' => $monthPast,
            'attendanceSummary' => $summary['attendanceSummary'],
            'roleAssignment' => $summary['roleAssignment'],
            'myAttendance' => $summary['myAttendance'],
        ]);
    }

    private function meetingsData(): array
    {
        $today = today();

        $upcoming = Db::fetchAll(
            'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                    p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom,
                    p.geo_link, p.note AS place_note,
                    u.name AS owner_name, u.address_term AS owner_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE m.date >= :today
             ORDER BY m.date ASC, m.id DESC
             LIMIT 30',
            [':today' => $today]
        );

        $past = Db::fetchAll(
            'SELECT m.*, p.title AS place_title, p.region, p.district, p.locality, p.street,
                    p.house, p.house_letter, p.entrance, p.floor, p.apartment, p.intercom,
                    p.geo_link, p.note AS place_note,
                    u.name AS owner_name, u.address_term AS owner_term
             FROM meetings m
             LEFT JOIN places p ON p.id = m.place_id
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE m.date < :today
             ORDER BY m.date DESC
             LIMIT 30',
            [':today' => $today]
        );

        $summary = $this->withSummary(array_merge($upcoming, $past));

        return [
            'upcoming' => $upcoming,
            'past' => $past,
            'attendanceSummary' => $summary['attendanceSummary'],
            'roleAssignment' => $summary['roleAssignment'],
            'myAttendance' => $summary['myAttendance'],
        ];
    }

    private function withSummary(array $allMeetings): array
    {
        $meetingIds = array_column($allMeetings, 'id');

        $attendanceSummary = [];
        $roleAssignment = [];
        if ($meetingIds) {
            $ids = implode(',', array_map('intval', $meetingIds));
            $ph = implode(',', array_fill(0, count(ATTENDANCE_ROLES), '?'));
            $marked = [];
            foreach (Db::fetchAll(
                "SELECT a.meeting_id, a.status, COUNT(*) AS cnt
                 FROM attendance a JOIN users u ON u.id = a.user_id
                 WHERE a.meeting_id IN ($ids) AND u.role IN ($ph)
                 GROUP BY a.meeting_id, a.status",
                ATTENDANCE_ROLES
            ) as $row) {
                $marked[$row['meeting_id']][$row['status']] = (int)$row['cnt'];
            }
            foreach (Db::fetchAll("SELECT mr.meeting_id, mr.role, u.id AS user_id, u.name, u.address_term FROM meeting_roles mr JOIN users u ON u.id = mr.user_id WHERE mr.meeting_id IN ($ids)") as $r) {
                $roleAssignment[$r['meeting_id']][] = $r;
            }
            foreach ($allMeetings as $m) {
                $attendanceSummary[$m['id']] = [
                    'present' => (int)($marked[$m['id']]['present'] ?? 0),
                    'online'  => (int)($marked[$m['id']]['online'] ?? 0),
                    'absent'  => (int)($marked[$m['id']]['absent'] ?? 0),
                    'guests'  => count_meeting_guests((string)$m['guests']),
                ];
            }
        }

        $myAttendance = [];
        if (Auth::id()) {
            foreach (Db::fetchAll('SELECT meeting_id, status FROM attendance WHERE user_id = ?', [Auth::id()]) as $row) {
                $myAttendance[$row['meeting_id']] = $row['status'];
            }
        }

        return [
            'attendanceSummary' => $attendanceSummary,
            'roleAssignment' => $roleAssignment,
            'myAttendance' => $myAttendance,
        ];
    }

    private function stripData(?string $m, ?string $sel = null): array
    {
        $monthNames = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
            'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
        $weekdays = [1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб', 7 => 'вс'];

        $year = (int)date('Y');
        $month = (int)date('n');
        if ($m !== null && preg_match('/^(\d{4})-(\d{2})$/', $m, $mm)) {
            $y = (int)$mm[1];
            $mo = (int)$mm[2];
            if ($mo >= 1 && $mo <= 12) {
                $year = $y;
                $month = $mo;
            }
        }

        $days = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $firstDow = (int)date('N', mktime(0, 0, 0, $month, 1, $year));

        $from = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01';
        $to = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-' . $days . 'T23:59';
        $meetingDays = [];
        foreach (Db::fetchAll('SELECT date FROM meetings WHERE date >= :from AND date <= :to', [':from' => $from, ':to' => $to]) as $row) {
            $meetingDays[substr($row['date'], 0, 10)] = true;
        }

        $prevTs = mktime(0, 0, 0, $month - 1, 1, $year);
        $nextTs = mktime(0, 0, 0, $month + 1, 1, $year);

        return [
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'firstDow' => $firstDow,
            'label' => $monthNames[$month] . ' ' . $year,
            'weekdays' => $weekdays,
            'meetingDays' => $meetingDays,
            'prev' => date('Y-m', $prevTs),
            'next' => date('Y-m', $nextTs),
            'today' => today(),
            'selected' => $sel,
        ];
    }
}