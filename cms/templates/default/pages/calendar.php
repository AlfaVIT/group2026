<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Календарь встреч</h1>
        <p class="text-body-secondary mb-0">Предстоящие и прошедшие встречи на выбранный месяц</p>
    </div>
    <?php if (Auth::can('manage_meetings')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= url('meeting_edit') ?>">Новая встреча</a>
            <a class="btn btn-outline-primary" href="<?= url('place_edit') ?>">Новое место</a>
        </div>
    <?php endif; ?>
</div>

<?php partial('calendar_strip', ['strip' => $strip]); ?>

<?php if (!$monthUpcoming && !$monthPast): ?>
    <div class="alert alert-info">В <?= e($strip['label']) ?> встреч нет.</div>
<?php elseif (!$selected): ?>
    <div class="alert alert-info">
        В <?= e($strip['label']) ?> встреч на предстоящие даты нет. Нажмите на дату со встречей, чтобы увидеть сведения.
    </div>
<?php else: ?>
    <h2 class="h5 mb-3">Выбранная встреча</h2>
    <?php partial('meeting_card', [
        'meeting' => $selected,
        'roleAssignment' => $roleAssignment,
        'attendanceSummary' => $attendanceSummary,
        'myAttendance' => $myAttendance,
    ]); ?>
<?php endif; ?>