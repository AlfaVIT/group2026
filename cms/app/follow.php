<?php

/**
 * Слежение за старшим: синхронная прокрутка текста вечерни и литургии.
 *
 * Старший на странице текста включает «включить слежение». Его позиция
 * прокрутки сохраняется как доля 0..1 (не в пикселях), потому что у участников
 * разный размер шрифта и разная высота окна. Остальные включают «следить за
 * старшим» и периодически опрашивают состояние: получив новую позицию, страница
 * подкручивается к той же доле текста.
 *
 * Состояние живёт в meeting_follow и привязано к встрече: разные встречи не
 * мешают друг другу. Ведущий всегда один — последний включивший «включить
 * слежение» (как в жизни: старший, который ведёт чтение).
 */

/** Сколько секунд без сигнала старший считается ушедшим со страницы. */
const FOLLOW_LEADER_TTL = 60;

function follow_is_senior(array $user): bool
{
    return ($user['role'] ?? '') === 'Старший';
}

/** Может ли текущий пользователь включать слежение (роль «Старший»). */
function follow_can_lead(): bool
{
    $user = Auth::user();
    return $user !== null && follow_is_senior($user);
}

/** Сохраняет/обновляет строку участия (у пользователя одна строка на встречу). */
function follow_upsert(int $meetingId, int $userId, int $isLeader, float $position): void
{
    $position = max(0.0, min(1.0, $position));
    Db::q(
        'INSERT INTO meeting_follow (meeting_id, user_id, is_leader, position, updated_at)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT (meeting_id, user_id) DO UPDATE SET
             is_leader = excluded.is_leader,
             position = excluded.position,
             updated_at = excluded.updated_at',
        [$meetingId, $userId, $isLeader, $position, time()]
    );
}

/** Убирает устаревшие состояния: без активного старшего слежение невозможно. */
function follow_clear_stale(int $meetingId): void
{
    $rows = Db::fetchAll(
        'SELECT is_leader, updated_at FROM meeting_follow WHERE meeting_id = ?',
        [$meetingId]
    );
    if (!$rows) {
        return;
    }
    $limit = time() - FOLLOW_LEADER_TTL;
    $active = false;
    foreach ($rows as $row) {
        if ((int)$row['is_leader'] === 1 && (int)$row['updated_at'] >= $limit) {
            $active = true;
            break;
        }
    }
    if ($active) {
        Db::q(
            'DELETE FROM meeting_follow WHERE meeting_id = ? AND is_leader = 1 AND updated_at < ?',
            [$meetingId, $limit]
        );
        return;
    }
    // старшего нет — сбрасываем подписки, иначе они «оживут» у следующего ведущего
    Db::q('DELETE FROM meeting_follow WHERE meeting_id = ?', [$meetingId]);
}

/** Включает/выключает ведущего; одновременно ведущим может быть только один. */
function follow_set_leader(int $meetingId, int $userId, bool $on, float $position = 0.0): void
{
    if ($on) {
        Db::transaction(function () use ($meetingId, $userId, $position): void {
            Db::q(
                'DELETE FROM meeting_follow WHERE meeting_id = ? AND user_id != ? AND is_leader = 1',
                [$meetingId, $userId]
            );
            follow_upsert($meetingId, $userId, 1, $position);
        });
        return;
    }
    Db::delete('meeting_follow', ['meeting_id' => $meetingId, 'user_id' => $userId]);
    // ведущий ушёл — следить больше не за кем
    Db::q('DELETE FROM meeting_follow WHERE meeting_id = ? AND is_leader = 0', [$meetingId]);
}

/** Включает/выключает подписку «следить за старшим» для участника. */
function follow_set_follower(int $meetingId, int $userId, bool $on): void
{
    if ($on) {
        follow_upsert($meetingId, $userId, 0, 0.0);
        return;
    }
    Db::delete('meeting_follow', ['meeting_id' => $meetingId, 'user_id' => $userId]);
}

/** Новая позиция прокрутки от ведущего (доля 0..1). */
function follow_update_position(int $meetingId, int $userId, float $position): void
{
    if (!follow_is_leader($meetingId, $userId)) {
        return;
    }
    Db::q(
        'UPDATE meeting_follow SET position = ?, updated_at = ? WHERE meeting_id = ? AND user_id = ?',
        [max(0.0, min(1.0, $position)), time(), $meetingId, $userId]
    );
}

/** Сигнал «я ещё на странице»: продлевает жизнь ведущего без смены позиции. */
function follow_heartbeat(int $meetingId, int $userId): void
{
    Db::q(
        'UPDATE meeting_follow SET updated_at = ? WHERE meeting_id = ? AND user_id = ? AND is_leader = 1',
        [time(), $meetingId, $userId]
    );
}

function follow_is_leader(int $meetingId, int $userId): bool
{
    return (int)(Db::value(
        'SELECT COUNT(*) FROM meeting_follow WHERE meeting_id = ? AND user_id = ? AND is_leader = 1',
        [$meetingId, $userId]
    ) ?? 0) > 0;
}

/**
 * Текущее состояние слежения для встречи и конкретного пользователя.
 *
 * leader — активный ведущий (или null), role — 'leader' | 'follower' | null,
 * can_lead — может ли пользователь вести (роль «Старший»), followers — сколько
 * участников сейчас следят.
 */
function follow_state(int $meetingId, ?int $userId, bool $canLead = false): array
{
    $limit = time() - FOLLOW_LEADER_TTL;
    $leader = Db::fetch(
        'SELECT f.user_id, f.position, f.updated_at, u.name, u.address_term
         FROM meeting_follow f JOIN users u ON u.id = f.user_id
         WHERE f.meeting_id = ? AND f.is_leader = 1 AND f.updated_at >= ?
         ORDER BY f.updated_at DESC LIMIT 1',
        [$meetingId, $limit]
    );

    $role = null;
    if ($userId && $leader) {
        if ((int)$leader['user_id'] === $userId) {
            $role = 'leader';
        } else {
            $myRow = Db::value(
                'SELECT is_leader FROM meeting_follow WHERE meeting_id = ? AND user_id = ?',
                [$meetingId, $userId]
            );
            if ($myRow !== null && (int)$myRow === 0) {
                $role = 'follower';
            }
        }
    }

    $followers = (int)(Db::value(
        'SELECT COUNT(*) FROM meeting_follow WHERE meeting_id = ? AND is_leader = 0',
        [$meetingId]
    ) ?? 0);

    return [
        'leader' => $leader ? [
            'id' => (int)$leader['user_id'],
            'name' => full_name(['address_term' => $leader['address_term'], 'name' => $leader['name']]),
            'position' => round((float)$leader['position'], 4),
            'ts' => (int)$leader['updated_at'],
        ] : null,
        'role' => $role,
        'can_lead' => $canLead,
        'followers' => $followers,
    ];
}
