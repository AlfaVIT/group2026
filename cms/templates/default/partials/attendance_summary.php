<?php
$statuses = $attendanceSummary[$meeting['id']] ?? [];
$myStatus = $myAttendance[$meeting['id']] ?? '';
$present = (int)($statuses['present'] ?? 0);
$online = (int)($statuses['online'] ?? 0);
$absent = (int)($statuses['absent'] ?? 0);
$items = [
    'present' => [$present, 'text-bg-success', 'очно'],
    'online'  => [$online, 'text-bg-info', 'онлайн'],
    'absent'  => [$absent, 'text-bg-danger', 'отсутствуют'],
];
?>
<div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
    <span class="fw-semibold small text-body-secondary">Явка:</span>
    <?php if ($present === 0 && $online === 0 && $absent === 0): ?>
        <span class="small text-body-secondary">отметок нет</span>
    <?php else: ?>
        <?php foreach ($items as $item): if (!$item[0]) continue; ?>
            <span class="badge text-nowrap <?= $item[1] ?>"><?= $item[0] ?> <?= $item[2] ?></span>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (Auth::can('mark_attendance') && $myStatus === ''): ?>
        <?php /* быстрая отметка: только пока участник не отмечался */ ?>
        <form method="post" action="<?= url('attendance_save') ?>" class="d-inline ms-auto"
              data-ajax-update="#meeting-<?= (int)$meeting['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
            <input type="hidden" name="status" value="present">
            <button type="submit" class="btn btn-sm btn-outline-success cms-btn-icon"
                    title="Отметить, что буду присутствовать очно"
                    aria-label="Отметить, что буду присутствовать очно">
                <?= ui_icon('check') ?><span class="cms-btn-label">Отметиться</span>
            </button>
        </form>
    <?php elseif ($myStatus !== '' && isset(ATTENDANCE_STATUSES[$myStatus])): ?>
        <span class="badge text-nowrap ms-auto <?= attendance_badge_class($myStatus) ?>"
              title="Ваша отметка"><?= e(ATTENDANCE_STATUSES[$myStatus]) ?></span>
    <?php endif; ?>
</div>