<?php
$series = $chart['series'] ?? [];
$articles = $chart['articles'] ?? array_keys($series);
$palette = ['#b08a2e', '#3d6ea5', '#6b8e4e', '#b85c5c', '#8a5bb0', '#2f9e9e', '#c97f2e', '#6a6a86', '#a83e6b', '#4d8a4d'];

$min = 0.0;
$max = 0.0;
$dates = [];
foreach ($series as $pts) {
    foreach ($pts as $p) {
        $min = min($min, (float)$p['balance']);
        $max = max($max, (float)$p['balance']);
        $dates[$p['date']] = true;
    }
}
$dates = array_keys($dates);
sort($dates, SORT_STRING);
$count = count($dates);
$seriesCount = count($articles);
?>
<?php if ($seriesCount === 0 || $count === 0): ?>
    <div class="text-center text-body-secondary small py-4">Данных пока нет — баланс казны появится после первых записей</div>
<?php elseif ($count === 1): ?>
    <div class="text-center py-4">
        <?php foreach ($articles as $i => $a): ?>
            <div class="small text-body-secondary mb-1">
                <span class="d-inline-block me-2" style="width:10px;height:10px;border-radius:50%;background:<?= $palette[$i % count($palette)] ?>;"></span>
                <?= e($a) ?>: <strong><?= money($series[$a][0]['balance']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php
    $W = 820;
    $H = 260;
    $pl = 10;
    $pr = 14;
    $pt = 18;
    $pb = 22;
    $innerW = $W - $pl - $pr;
    $innerH = $H - $pt - $pb;

    $range = $max - $min;
    if ($range <= 0) {
        $range = 1;
    }
    $pad = $range * 0.04;

    $px = fn($i) => $pl + ($count > 1 ? $innerW * $i / ($count - 1) : 0);
    $py = fn($v) => $pt + $innerH * (($max + $pad - $v) / ($range + 2 * $pad));
    $baseY = $py(0.0);
    $zeroVisible = $min < 0 && $max > 0;

    $poly = [];
    foreach ($articles as $a) {
        $line = '';
        foreach ($series[$a] as $j => $p) {
            $line .= ($j === 0 ? '' : ' ') . sprintf('%.1f,%.1f', $px($j), $py($p['balance']));
        }
        $poly[$a] = $line;
    }
    ?>
    <div class="treasury-chart">
        <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="График состояния казны по статьям" style="width:100%;height:auto;">
            <g stroke="#b08a2e" stroke-width="1.2" opacity="0.45" stroke-dasharray="4 6">
                <line x1="<?= $pl ?>" y1="<?= $py($max + $pad) ?>" x2="<?= $W - $pr ?>" y2="<?= $py($max + $pad) ?>"/>
                <line x1="<?= $pl ?>" y1="<?= $py(0.0) ?>" x2="<?= $W - $pr ?>" y2="<?= $py(0.0) ?>"/>
                <line x1="<?= $pl ?>" y1="<?= $py($min - $pad) ?>" x2="<?= $W - $pr ?>" y2="<?= $py($min - $pad) ?>"/>
            </g>

            <?php if ($zeroVisible): ?>
                <line x1="<?= $pl ?>" y1="<?= sprintf('%.1f', $baseY) ?>" x2="<?= $W - $pr ?>" y2="<?= sprintf('%.1f', $baseY) ?>"
                      stroke="#8d99ae" stroke-width="1" stroke-dasharray="3 5"/>
            <?php endif; ?>

            <?php foreach ($articles as $i => $a): ?>
                <?php $color = $palette[$i % count($palette)]; $pts = $series[$a]; ?>
                <polyline points="<?= $poly[$a] ?>" fill="none" stroke="<?= $color ?>" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
                <?php foreach ($pts as $j => $p): ?>
                    <circle cx="<?= sprintf('%.1f', $px($j)) ?>" cy="<?= sprintf('%.1f', $py($p['balance'])) ?>" r="2.8"
                            fill="#fffdf6" stroke="<?= $color ?>" stroke-width="1.4">
                        <title><?= e($a) ?> — <?= e(date('d.m.Y', strtotime($p['date']))) ?>: <?= money($p['balance']) ?></title>
                    </circle>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <text x="<?= $pl ?>" y="<?= sprintf('%.1f', $py($max + $pad) - 5) ?>" fill="#8a7d4d" font-size="11">макс: <?= money($max) ?></text>
            <text x="<?= $pl ?>" y="<?= sprintf('%.1f', $py($min - $pad) + 15) ?>" fill="#8a7d4d" font-size="11">мин: <?= money($min) ?></text>
            <text x="<?= $W - $pr ?>" y="<?= $H - 5 ?>" text-anchor="end" fill="#8a7d4d" font-size="11">
                на <?= e(date('d.m.Y', strtotime($dates[$count - 1]))) ?>
            </text>
        </svg>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($articles as $i => $a): ?>
                <span class="badge text-bg-light border">
                    <span class="d-inline-block me-1" style="width:14px;height:3px;background:<?= $palette[$i % count($palette)] ?>;"></span>
                    <?= e($a) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>