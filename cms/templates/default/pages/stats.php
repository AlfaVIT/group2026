<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Статистика</h1>
        <p class="text-body-secondary small mb-0">Активность пользователей: последние 50 посещений</p>
    </div>
</div>

<div class="card shadow-sm">
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
