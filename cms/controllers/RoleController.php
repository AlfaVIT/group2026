<?php

class RoleController
{
    public function save(): void
    {
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        if (!Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            exit('Встреча не найдена');
        }

        $submitted = (array)($_POST['roles'] ?? []);
        $validUserIds = array_map('intval', array_column(Db::fetchAll('SELECT id FROM users WHERE is_active = 1'), 'id'));
        $validUserIds = array_flip($validUserIds);

        $toAssign = [];
        foreach ($submitted as $userId => $userRoles) {
            if (!isset($validUserIds[(int)$userId])) {
                continue;
            }
            $uid = (int)$userId;
            foreach ((array)$userRoles as $role) {
                if (in_array($role, MEETING_ROLES, true)) {
                    $toAssign[] = [$meetingId, $uid, $role];
                }
            }
        }

        Db::transaction(function () use ($meetingId, $toAssign) {
            Db::delete('meeting_roles', ['meeting_id' => $meetingId]);
            foreach ($toAssign as [$m, $u, $r]) {
                Db::insert('meeting_roles', ['meeting_id' => $m, 'user_id' => $u, 'role' => $r]);
            }
        });

        set_flash('Роли на встречу сохранены');
        redirect(url('meeting', ['id' => $meetingId]));
    }
}