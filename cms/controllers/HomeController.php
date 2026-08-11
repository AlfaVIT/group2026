<?php

class HomeController
{
    public function index(): void
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

        $allMeetings = array_merge($upcoming, $past);
        $meetingIds = array_column($allMeetings, 'id');

        $attendanceSummary = [];
        $roleAssignment = [];
        if ($meetingIds) {
            $ids = implode(',', array_map('intval', $meetingIds));
            $attendanceSummary = [];
            foreach (Db::fetchAll("SELECT meeting_id, status, COUNT(*) AS cnt FROM attendance WHERE meeting_id IN ($ids) GROUP BY meeting_id, status") as $row) {
                $attendanceSummary[$row['meeting_id']][$row['status']] = (int)$row['cnt'];
            }
            $roles = Db::fetchAll("SELECT mr.meeting_id, mr.role, u.id AS user_id, u.name, u.address_term FROM meeting_roles mr JOIN users u ON u.id = mr.user_id WHERE mr.meeting_id IN ($ids)");
            foreach ($roles as $r) {
                $roleAssignment[$r['meeting_id']][] = $r;
            }
        }

        $myAttendance = [];
        if (Auth::id()) {
            foreach (Db::fetchAll('SELECT meeting_id, status FROM attendance WHERE user_id = ?', [Auth::id()]) as $row) {
                $myAttendance[$row['meeting_id']] = $row['status'];
            }
        }

        view('home', [
            'title' => Setting::siteName(),
            'upcoming' => $upcoming,
            'past' => $past,
            'attendanceSummary' => $attendanceSummary,
            'roleAssignment' => $roleAssignment,
            'myAttendance' => $myAttendance,
        ]);
    }
}