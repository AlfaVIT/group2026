<?php

/**
 * Состояние слежения за старшим на странице текста вечерни/литургии.
 *
 * POST (AJAX, JSON):
 *   meeting_id — встреча;
 *   lead=0|1   — старший включает/выключает слежение (только роль «Старший»);
 *   follow=0|1 — участник подписывается/отписывается от слежения;
 *   pos=0..1   — новая доля прокрутки от ведущего.
 *
 * Без lead/follow/pos запрос работает как «я на странице» (heartbeat ведущего)
 * и как опрос состояния для всех остальных.
 */
class FollowController
{
    public function sync(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $meetingId = (int)post('meeting_id', 0);
        $user = Auth::user();
        if (!$meetingId || !Db::fetch('SELECT id FROM meetings WHERE id = ?', [$meetingId])) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Встреча не найдена'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Нужно войти на сайт'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)$user['id'];
        $canLead = follow_is_senior($user);

        follow_clear_stale($meetingId);

        $pos = null;
        if (isset($_POST['pos']) && is_scalar($_POST['pos'])) {
            $pos = (float)$_POST['pos'];
            if (!is_finite($pos)) {
                $pos = null;
            }
        }

        $lead = $_POST['lead'] ?? null;
        if ($lead !== null && is_scalar($lead) && $canLead) {
            follow_set_leader($meetingId, $userId, (string)$lead === '1', $pos ?? 0.0);
        } elseif ($pos !== null) {
            follow_update_position($meetingId, $userId, $pos);
        } else {
            follow_heartbeat($meetingId, $userId);
        }

        $follow = $_POST['follow'] ?? null;
        if ($follow !== null && is_scalar($follow)) {
            follow_set_follower($meetingId, $userId, (string)$follow === '1');
        }

        echo json_encode(
            ['ok' => true] + follow_state($meetingId, $userId, $canLead),
            JSON_UNESCAPED_UNICODE
        );
    }
}
