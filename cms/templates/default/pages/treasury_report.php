<?php
$modeLabel = $mode === 'budget' ? 'бюджетный (по периоду)' : 'кассовый (по дате)';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Консолидированный отчёт</h1>
        <p class="text-body-secondary small mb-0">
            Взносы и возмещения участников по статьям · <?= e($modeLabel) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-success" href="<?= url('treasury_report_export', ['from' => $from, 'to' => $to, 'mode' => $mode]) ?>">Экспорт в Excel</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury') ?>">← Казна</a>
    </div>
</div>

<form method="get" action="<?= url('treasury_report') ?>" class="row g-2 mb-3">
    <input type="hidden" name="p" value="treasury_report">
    <div class="col-6 col-md-3">
        <div class="input-group input-group-sm">
            <span class="input-group-text">от</span>
            <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="input-group input-group-sm">
            <span class="input-group-text">по</span>
            <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select class="form-select form-select-sm" name="mode">
            <option value="cash" <?= $mode === 'cash' ? 'selected' : '' ?>>Кассовый (по дате)</option>
            <option value="budget" <?= $mode === 'budget' ? 'selected' : '' ?>>Бюджетный (по периоду)</option>
        </select>
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary flex-grow-1" type="submit">Показать</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury_report') ?>">Сброс</a>
    </div>
</form>

<?php if (!$articles): ?>
    <p class="text-body-secondary small">За выбранный период данных нет.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th rowspan="2" class="align-middle">Участник</th>
                <?php foreach ($articles as $a): $hasExp = (float)($articleTotals[$a]['expense'] ?? 0) > 0; ?>
                    <th colspan="<?= $hasExp ? 3 : 1 ?>" class="text-center"><?= e($a) ?></th>
                <?php endforeach; ?>
                <th rowspan="2" class="align-middle text-end">Расходы</th>
            </tr>
            <tr>
                <?php foreach ($articles as $a): $hasExp = (float)($articleTotals[$a]['expense'] ?? 0) > 0; ?>
                    <th class="text-end small">Взнос</th>
                    <?php if ($hasExp): ?>
                        <th class="text-end small">Возмещено</th>
                        <th class="text-end small">Остаток</th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): $uid = (int)$u['id']; ?>
                <tr>
                    <td class="small"><?= e(full_name($u)) ?></td>
                    <?php foreach ($articles as $a): ?>
                        <?php
                        $hasExp = (float)($articleTotals[$a]['expense'] ?? 0) > 0;
                        $vznos = (float)($contrib[$uid][$a] ?? 0);
                        $vozm = (float)($expense[$uid][$a] ?? 0);
                        ?>
                        <td class="text-end"><?= $vznos != 0.0 ? money($vznos) : '—' ?></td>
                        <?php if ($hasExp): ?>
                            <td class="text-end"><?= $vozm != 0.0 ? money($vozm) : '—' ?></td>
                            <td class="text-end fw-semibold <?= ($vznos - $vozm) < 0 ? 'text-danger' : '' ?>">
                                <?= money($vznos - $vozm) ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <?= !empty($userExpenseTotal[$uid]) ? money($userExpenseTotal[$uid]) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold">
            <tr>
                <td>Итого</td>
                <?php foreach ($articles as $a): ?>
                    <?php
                    $hasExp = (float)($articleTotals[$a]['expense'] ?? 0) > 0;
                    $inc = (float)($articleTotals[$a]['income'] ?? 0);
                    $exp = (float)($articleTotals[$a]['expense'] ?? 0);
                    ?>
                    <td class="text-end"><?= money($inc) ?></td>
                    <?php if ($hasExp): ?>
                        <td class="text-end"><?= money($exp) ?></td>
                        <td class="text-end <?= ($inc - $exp) < 0 ? 'text-danger' : '' ?>"><?= money($inc - $exp) ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
                <td class="text-end"><?= money(array_sum($userExpenseTotal)) ?></td>
            </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>
