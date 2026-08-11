<?php
$statuses = $attendanceSummary[$meeting['id']] ?? [];
$total = array_sum($statuses);
?>
<div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
    <span class="fw-semibold small text-body-secondary">Явка:</span>
    <?php if ($total === 0): ?>
        <span class="small text-body-secondary">отметок пока нет</span>
    <?php else: ?>
        <?php
        $map = ['present' => 'text-bg-success', 'online' => 'text-bg-info', 'absent' => 'text-bg-danger'];
        $labels = ['present' => 'присутствуют', 'online' => 'онлайн', 'absent' => 'отсутствуют'];
        ?>
        <?php foreach ($map as $status => $class): ?>
            <?php if (!empty($statuses[$status])): ?>
                <span class="badge <?= $class ?>"><?= (int)$statuses[$status] ?> <?= $labels[$status] ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>