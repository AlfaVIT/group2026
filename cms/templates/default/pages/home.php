<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Календарь встреч</h1>
        <p class="text-body-secondary mb-0">Расписание и подробная информация по каждой встрече</p>
    </div>
    <?php if (Auth::can('manage_meetings')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= url('meeting_edit') ?>">Новая встреча</a>
            <a class="btn btn-outline-primary" href="<?= url('place_edit') ?>">Новое место</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$upcoming && !$past): ?>
    <div class="alert alert-info">Встреч пока нет.<?= Auth::can('manage_meetings') ? ' Создайте первую встречу кнопкой «Новая встреча».' : '' ?></div>
<?php endif; ?>

<?php if ($upcoming): ?>
    <h2 class="h5 text-success mb-3">Предстоящие встречи</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-4">
        <?php foreach ($upcoming as $meeting): ?>
            <div class="col">
                <?php partial('meeting_card', [
                    'meeting' => $meeting,
                    'roleAssignment' => $roleAssignment,
                    'attendanceSummary' => $attendanceSummary,
                    'myAttendance' => $myAttendance,
                ]); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($past): ?>
    <h2 class="h5 text-body-secondary mb-3">Прошедшие встречи</h2>
    <div class="accordion" id="pastAccordion">
        <?php foreach ($past as $i => $meeting): ?>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#past-<?= (int)$meeting['id'] ?>" aria-expanded="false">
                        <span class="d-flex flex-wrap justify-content-between w-100 gap-2 pe-2">
                            <span><?= rdate($meeting['date']) ?><?= $meeting['topic'] !== '' ? ' — ' . e($meeting['topic']) : '' ?></span>
                            <span class="small text-body-secondary"><?= sum_col($attendanceSummary[$meeting['id']] ?? []) ?> отметок</span>
                        </span>
                    </button>
                </h3>
                <div id="past-<?= (int)$meeting['id'] ?>" class="accordion-collapse collapse"
                     data-bs-parent="#pastAccordion">
                    <div class="accordion-body">
                        <?php partial('meeting_card', [
                            'meeting' => $meeting,
                            'roleAssignment' => $roleAssignment,
                            'attendanceSummary' => $attendanceSummary,
                            'myAttendance' => $myAttendance,
                            'compact' => true,
                        ]); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>