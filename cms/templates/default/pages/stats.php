<?php
$sortLink = static function (string $key) use ($sort, $dir): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return url('stats', ['sort' => $key, 'dir' => $nextDir]);
};
$sortArrow = static function (string $key) use ($sort, $dir): string {
    if ($sort !== $key) {
        return '';
    }
    return $dir === 'asc' ? ' ▲' : ' ▼';
};
$colspan = count($months) + 3;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Статистика</h1>
        <p class="text-body-secondary small mb-0">Входы участников по месяцам и история посещений</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Входы по месяцам</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>
                        <a class="text-reset text-decoration-none"
                           href="<?= e($sortLink('name')) ?>">Участник<?= $sortArrow('name') ?></a>
                    </th>
                    <?php foreach ($months as $month): ?>
                        <th class="text-end text-nowrap small"><?= e($monthLabels[$month] ?? $month) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Всего</th>
                    <th class="text-end text-nowrap">
                        <a class="text-reset text-decoration-none"
                           href="<?= e($sortLink('last')) ?>">Последний вход<?= $sortArrow('last') ?></a>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="<?= $colspan ?>" class="text-body-secondary small">Входов пока не было</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uid = (int)$u['id'];
                    $data = $byUser[$uid] ?? ['months' => [], 'total' => 0, 'last' => ''];
                    ?>
                    <tr>
                        <td class="small">
                            <?= e(full_name($u)) ?>
                            <?php if ((int)$u['is_active'] !== 1): ?>
                                <span class="badge text-bg-secondary">отключён</span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($months as $month): ?>
                            <?php $count = (int)($data['months'][$month] ?? 0); ?>
                            <td class="text-end small<?= $count === 0 ? ' text-body-tertiary' : '' ?>">
                                <?= $count > 0 ? $count : '—' ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-end fw-semibold"><?= (int)$data['total'] > 0 ? (int)$data['total'] : '—' ?></td>
                        <td class="small text-nowrap text-end">
                            <?= $data['last'] !== '' ? e(date('d.m.Y H:i', strtotime($data['last']))) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-text mt-2">
            Столбцы — месяцы, в ячейках — число входов. Прочерк означает, что входов не было.
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header p-0">
        <button class="btn btn-link w-100 text-start text-decoration-none py-2 px-3 fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2"
                type="button" data-bs-toggle="collapse" data-bs-target="#visitsLog"
                aria-expanded="false" aria-controls="visitsLog">
            <span>Входы</span>
            <span class="small text-body-secondary">последние <?= count($visits) ?> записей</span>
        </button>
    </div>
    <div class="collapse" id="visitsLog">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Когда</th>
                        <th>Участник</th>
                        <th>IP</th>
                        <th>Устройство</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$visits): ?>
                        <tr><td colspan="4" class="text-body-secondary small">Записей пока нет</td></tr>
                    <?php endif; ?>
                    <?php foreach ($visits as $v): ?>
                        <tr>
                            <td class="small text-nowrap"><?= e(date('d.m.Y H:i', strtotime($v['created_at']))) ?></td>
                            <td class="small"><?= e(full_name($v)) ?></td>
                            <td class="small"><?= e($v['ip'] !== '' ? $v['ip'] : '—') ?></td>
                            <td class="small" title="<?= e($v['user_agent']) ?>"><?= e(visitor_device($v['user_agent'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
