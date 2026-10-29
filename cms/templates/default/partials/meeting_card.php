<?php
$meeting = $meeting;
$roles = $roleAssignment[$meeting['id']] ?? [];
$address = address_parts($meeting, true);
$maps = $meeting['geo_link'] !== '' ? $meeting['geo_link'] : yandex_maps_link($meeting);
$my = $myAttendance[$meeting['id']] ?? null;
?>
<div class="card shadow-sm meeting-card" id="meeting-<?= (int)$meeting['id'] ?>">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><?= rdate($meeting['date']) ?></span>
        <span class="d-flex align-items-center gap-2">
            <?php if ($meeting['tag'] !== ''): ?>
                <span class="tag-badge"><?= e($meeting['tag']) ?></span>
            <?php endif; ?>
            <span class="small text-body-secondary"><?= e(date('H:i', strtotime($meeting['date']))) ?></span>
        </span>
    </div>
    <div class="card-body">
        <?php $cardTitle = $meeting['meeting_type'] !== '' ? $meeting['meeting_type'] : $meeting['topic']; ?>
        <?php if ($cardTitle !== ''): ?>
            <h5 class="card-title"><?= e($cardTitle) ?></h5>
        <?php endif; ?>

        <?php if ($meeting['place_id']): ?>
            <?php if ($address): ?>
                <div class="small">
                    <span class="text-body-secondary">Место:</span>
                    <span><?= e(implode(', ', $address)) ?></span>
                </div>
            <?php endif; ?>
            <?php $widget = yandex_map_widget($meeting); ?>
            <?php if ($widget !== ''): ?>
                <div class="mt-2">
                    <iframe src="<?= e($widget) ?>" class="cms-mini-map" loading="lazy"
                            title="Карта" allowfullscreen></iframe>
                    <div class="small mt-1">
                        <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Открыть карту</a>
                    </div>
                </div>
            <?php elseif ($maps !== ''): ?>
                <div class="small mt-1">
                    <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Показать на карте</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($meeting['meeting_type'] !== '' && $meeting['topic'] !== ''): ?>
            <div class="mt-2 small"><span class="text-body-secondary">Тема:</span> <?= e($meeting['topic']) ?></div>
        <?php endif; ?>

        <?php partial('materials_list', ['meeting' => $meeting]); ?>

        <?php $guestNames = meeting_guest_names((string)$meeting['guests']); ?>
        <?php if ($guestNames): ?>
            <div class="mt-2 small">
                <span class="text-body-secondary">Гости:</span> <?= e(implode(', ', $guestNames)) ?>
            </div>
        <?php endif; ?>

        <?php $pc = (int)($attendanceSummary[$meeting['id']]['present'] ?? 0) + (int)($attendanceSummary[$meeting['id']]['guests'] ?? 0); ?>
        <?php if ($pc > 0): ?>
            <div class="mt-1 small">
                <span class="text-body-secondary">Участников:</span> <?= $pc ?>
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
    </div>
</div>