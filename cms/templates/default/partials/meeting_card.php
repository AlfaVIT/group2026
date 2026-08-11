<?php
$meeting = $meeting;
$roles = $roleAssignment[$meeting['id']] ?? [];
$address = address_parts($meeting);
$maps = $meeting['geo_link'] !== '' ? $meeting['geo_link'] : yandex_maps_link($meeting);
$my = $myAttendance[$meeting['id']] ?? null;
?>
<div class="card h-100 shadow-sm meeting-card" id="meeting-<?= (int)$meeting['id'] ?>">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><?= rdate($meeting['date']) ?></span>
        <span class="small text-body-secondary"><?= e(date('H:i', strtotime($meeting['date']))) ?></span>
    </div>
    <div class="card-body">
        <?php if ($meeting['topic'] !== ''): ?>
            <h5 class="card-title"><?= e($meeting['topic']) ?></h5>
        <?php endif; ?>

        <?php if ($meeting['place_title'] !== ''): ?>
            <div class="small">
                <span class="text-body-secondary">Место:</span>
                <span class="fw-semibold"><?= e($meeting['place_title']) ?></span>
                <?php if ($meeting['owner_name']): ?>
                    <span class="text-body-secondary">(хозяин: <?= e(full_name(['address_term' => $meeting['owner_term'], 'name' => $meeting['owner_name']])) ?>)</span>
                <?php endif; ?>
            </div>
            <?php if ($address): ?>
                <div class="small text-body-secondary"><?= e(implode(', ', $address)) ?></div>
            <?php endif; ?>
            <?php if ($maps !== ''): ?>
                <div class="small">
                    <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Показать на карте</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php partial('materials_list', ['meeting' => $meeting]); ?>

        <?php if ($meeting['guests'] !== ''): ?>
            <div class="mt-2 small">
                <span class="text-body-secondary">Гости:</span> <?= e($meeting['guests']) ?>
            </div>
        <?php endif; ?>

        <?php if ((int)$meeting['participants_count'] > 0): ?>
            <div class="mt-1 small">
                <span class="text-body-secondary">Участников:</span> <?= (int)$meeting['participants_count'] ?>
            </div>
        <?php endif; ?>

        <?php if ($meeting['stream_url'] !== ''): ?>
            <div class="mt-1">
                <a class="btn btn-sm btn-outline-danger" href="<?= e($meeting['stream_url']) ?>" target="_blank" rel="noopener">
                    Смотреть трансляцию
                </a>
            </div>
        <?php endif; ?>

        <?php partial('role_badges', ['roles' => $roles]); ?>
        <?php partial('attendance_summary', ['meeting' => $meeting, 'attendanceSummary' => $attendanceSummary]); ?>

        <?php if ($my): ?>
            <div class="mt-2 small">
                <span class="badge <?= attendance_badge_class($my) ?>"><?= ATTENDANCE_STATUSES[$my] ?? $my ?></span>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="<?= url('meeting', ['id' => $meeting['id']]) ?>">Подробнее</a>
        <?php if (Auth::can('manage_meetings')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting_edit', ['id' => $meeting['id']]) ?>">Изменить</a>
            <form method="post" action="<?= url('meeting_delete') ?>" class="d-inline"
                  onsubmit="return confirm('Удалить встречу?');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>