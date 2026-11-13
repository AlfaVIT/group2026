<?php
$meeting = $meeting;
$roles = $roleAssignment[$meeting['id']] ?? [];
$address = address_parts($meeting, true);
$maps = safe_url($meeting['geo_link'] ?? '') ?: yandex_maps_link($meeting);
?>
<div class="card shadow-sm meeting-card" id="meeting-<?= (int)$meeting['id'] ?>">
    <a class="card-header card-header-link d-flex flex-wrap justify-content-between align-items-center gap-2"
       href="<?= url('meeting', ['id' => (int)$meeting['id']]) ?>"
       title="Открыть страницу встречи">
        <span class="fw-semibold"><?= rdate($meeting['date']) ?></span>
        <span class="d-flex align-items-center gap-2">
            <?php if ($meeting['tag'] !== ''): ?>
                <span class="tag-badge"><?= e($meeting['tag']) ?></span>
            <?php endif; ?>
            <span class="small text-body-secondary"><?= e(date('H:i', strtotime($meeting['date']))) ?></span>
            <span class="cms-card-arrow" aria-hidden="true">›</span>
        </span>
    </a>
    <div class="card-body">
        <?php $cardTitle = $meeting['meeting_type'] !== '' ? $meeting['meeting_type'] : $meeting['topic']; ?>
        <?php if ($cardTitle !== ''): ?>
            <h5 class="card-title"><?= e($cardTitle) ?></h5>
        <?php endif; ?>

        <?php if ($meeting['place_id']): ?>
            <?php if ($address): ?>
                <div class="small d-flex align-items-center gap-2">
                    <?php $placeAvatar = place_avatar_url([
                        'owner_avatar' => $meeting['place_owner_avatar'] ?? '',
                        'first_photo' => $meeting['place_first_photo'] ?? '',
                    ]); ?>
                    <?php if ($placeAvatar !== ''): ?>
                        <img class="cms-place-avatar cms-place-avatar-sm" src="<?= e($placeAvatar) ?>"
                             alt="" loading="lazy">
                    <?php else: ?>
                        <span class="cms-place-avatar cms-place-avatar-sm cms-place-avatar-empty"
                              aria-hidden="true"><?= ui_icon('home', 16) ?></span>
                    <?php endif; ?>
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

        <?php partial('role_badges', ['roles' => $roles]); ?>
        <?php partial('attendance_summary', ['meeting' => $meeting, 'attendanceSummary' => $attendanceSummary, 'myAttendance' => $myAttendance]); ?>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary cms-btn-icon" href="<?= url('meeting', ['id' => $meeting['id']]) ?>"
           title="Подробнее о встрече" aria-label="Подробнее о встрече">
            <?= ui_icon('eye') ?><span class="cms-btn-label">Подробнее</span>
        </a>
        <?php
        // как на странице встречи: Android — intent в календарь, остальные — файл .ics
        $calHref = is_android_client() ? android_calendar_intent($meeting) : '';
        if ($calHref === '') {
            $calHref = url('meeting_ics', ['id' => $meeting['id']]);
        }
        ?>
        <a class="btn btn-sm btn-success cms-btn-icon" href="<?= e($calHref) ?>"
           title="Добавить в календарь" aria-label="Добавить в календарь">
            <?= ui_icon('calendar') ?><span class="cms-btn-label">Добавить в календарь</span>
        </a>
        <?php $streamLink = meeting_stream_url($meeting); ?>
        <?php if ($streamLink !== ''): ?>
            <?php $streamCommon = meeting_stream_is_common($meeting); ?>
            <a class="btn btn-sm btn-danger cms-btn-icon" href="<?= e($streamLink) ?>" target="_blank" rel="noopener"
               title="<?= $streamCommon ? 'Подключиться к общей трансляции (ссылка из настроек)' : 'Смотреть трансляцию' ?>"
               aria-label="<?= $streamCommon ? 'Подключиться к общей трансляции' : 'Смотреть трансляцию' ?>">
                <?= ui_icon('video') ?><span class="cms-btn-label"><?= $streamCommon ? 'Подключиться к трансляции' : 'Смотреть трансляцию' ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>