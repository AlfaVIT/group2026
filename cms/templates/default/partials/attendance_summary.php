<?php
$statuses = $attendanceSummary[$meeting['id']] ?? [];
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
    <?php if (Auth::can('mark_attendance')): ?>
        <form method="post" action="<?= url('attendance_save') ?>" class="d-inline"
              data-ajax-update="#meeting-<?= (int)$meeting['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
            <input type="hidden" name="status" value="present">
            <button type="submit" class="btn btn-link btn-sm p-0 fw-semibold small"
                    title="Отметить себя присутствующим очно">Явка</button>
        </form>
    <?php else: ?>
        <span class="fw-semibold small text-body-secondary">Явка:</span>
    <?php endif; ?>
    <?php if ($present === 0 && $online === 0 && $absent === 0): ?>
        <span class="small text-body-secondary">отметок нет</span>
    <?php else: ?>
        <?php foreach ($items as $item): if (!$item[0]) continue; ?>
            <span class="badge <?= $item[1] ?>"><?= $item[0] ?> <?= $item[2] ?></span>
        <?php endforeach; ?>
    <?php endif; ?>
</div>