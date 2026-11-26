<?php
/**
 * Плавающий блок слежения за старшим (правый нижний угол).
 *
 * Показывается на страницах с текстом вечерни/литургии:
 *  - старшему — переключатель «Включить слежение»;
 *  - остальным участникам — «Следить за старшим», когда старший ведёт.
 *
 * $follow — состояние из follow_state(), $meetingId — текущая встреча.
 */
$follow = $follow ?? ['leader' => null, 'role' => null, 'can_lead' => false, 'followers' => 0];
$canLead = !empty($follow['can_lead']);
$leader = $follow['leader'] ?? null;
$role = (string)($follow['role'] ?? '');
$canFollow = $leader !== null && $role !== 'leader';
$visible = $canLead || $canFollow || $role === 'leader';
$isFollowing = $role === 'follower' && $leader !== null;
?>
<div id="cms-follow" class="cms-follow"<?= $visible ? '' : ' hidden' ?>
     data-meeting-id="<?= (int)$meetingId ?>"
     data-url="<?= e(url('follow_sync')) ?>"
     data-csrf="<?= e(csrf_token()) ?>"
     data-can-lead="<?= $canLead ? '1' : '0' ?>"
     data-role="<?= e($role) ?>"
     data-leader="<?= e(json_encode($leader, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="cms-follow-title mb-1">Слежение</div>
    <?php if ($canLead): ?>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="cms-follow-lead"
                   <?= $role === 'leader' ? 'checked' : '' ?>>
            <label class="form-check-label" for="cms-follow-lead">Включить слежение</label>
        </div>
    <?php endif; ?>
    <div class="form-check form-switch" id="cms-follow-follow-wrap"<?= $canFollow ? '' : ' hidden' ?>>
        <input class="form-check-input" type="checkbox" role="switch" id="cms-follow-follow"
               <?= $isFollowing ? 'checked' : '' ?>>
        <label class="form-check-label" for="cms-follow-follow">Следить за старшим</label>
    </div>
    <div class="cms-follow-status small text-body-secondary" id="cms-follow-status"></div>
</div>
