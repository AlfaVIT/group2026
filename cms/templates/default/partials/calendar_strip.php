<?php
// $strip: year, month, days, firstDow, label, weekdays, meetingDays, prev, next, today, selected
$page = $strip['page'] ?? 'calendar';
$monthKey = $strip['year'] . '-' . str_pad((string)$strip['month'], 2, '0', STR_PAD_LEFT);
?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($page, ['m' => $strip['prev']])) ?>"
               title="Предыдущий месяц">&laquo;</a>
            <span class="fw-semibold"><?= e($strip['label']) ?></span>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($page, ['m' => $strip['next']])) ?>"
               title="Следующий месяц">&raquo;</a>
        </div>
        <span class="small text-body-secondary">нажмите на дату со встречей — сведения ниже</span>
    </div>
    <div class="card-body cms-cal-strip-conteiner">
        <div class="cms-cal-strip">
            <?php for ($d = 1; $d <= $strip['days']; $d++): ?>
                <?php
                $dow = ($strip['firstDow'] + $d - 2) % 7 + 1;
                $date = $monthKey . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                $cls = 'cms-cal-cell';
                $hasMeeting = isset($strip['meetingDays'][$date]);
                if ($hasMeeting) {
                    $cls .= ' has-meeting';
                }
                if ($date === $strip['today']) {
                    $cls .= ' today';
                }
                if (($strip['selected'] ?? null) === $date) {
                    $cls .= ' selected';
                }
                $href = $hasMeeting ? e(url($page, ['m' => $monthKey, 'sel' => $date])) : '';
                ?>
                <?php if ($href): ?>
                    <a class="<?= $cls ?>" href="<?= $href ?>" title="<?= e($date) ?>">
                <?php else: ?>
                    <div class="<?= $cls ?>" title="<?= e($date) ?>">
                <?php endif; ?>
                    <span class="cms-cal-d"><?= $d ?></span>
                    <span class="cms-cal-w"><?= $strip['weekdays'][$dow] ?></span>
                <?php if ($href): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</div>