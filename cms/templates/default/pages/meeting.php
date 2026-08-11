<?php
$m = $meeting;
$address = address_parts($m);
$maps = $m['geo_link'] !== '' ? $m['geo_link'] : yandex_maps_link($m);
$materials = decode_materials($m['materials']);
$rolesByRole = [];
foreach ($roles as $r) {
    $rolesByRole[$r['role']][] = $r;
}
$assigned = [];
foreach ($roles as $r) {
    $assigned[$r['user_id']][$r['role']] = true;
}
$attendanceByUser = [];
foreach ($attendance as $a) {
    $attendanceByUser[$a['user_id']] = $a;
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0"><?= rdate($m['date']) ?></h1>
    <?php if (Auth::can('manage_meetings')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting_edit', ['id' => $m['id']]) ?>">Изменить</a>
            <form method="post" action="<?= url('meeting_delete') ?>" onsubmit="return confirm('Удалить встречу?');">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <?php if ($m['topic'] !== ''): ?>
                    <h2 class="h5"><?= e($m['topic']) ?></h2>
                <?php endif; ?>

                <?php if ($m['place_id']): ?>
                    <div class="mb-2">
                        <div class="fw-semibold">Место проведения</div>
                        <div><?= e($m['place_title']) ?>
                            <?php if ($m['owner_name']): ?>
                                <span class="text-body-secondary">— хозяин: <?= e(full_name(['address_term' => $m['owner_term'], 'name' => $m['owner_name']])) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($address): ?>
                            <div class="text-body-secondary"><?= e(implode(', ', $address)) ?></div>
                        <?php endif; ?>
                        <?php if ($maps !== ''): ?>
                            <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Показать на карте</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php partial('materials_list', ['meeting' => $m]); ?>

                <?php if ($m['guests'] !== ''): ?>
                    <div class="mt-2"><span class="text-body-secondary">Гости:</span> <?= e($m['guests']) ?></div>
                <?php endif; ?>

                <div class="mt-2">
                    <span class="text-body-secondary">Планируется участников:</span> <?= (int)$m['participants_count'] ?: '—' ?>
                </div>

                <?php if ($m['stream_url'] !== ''): ?>
                    <a class="btn btn-danger mt-3" href="<?= e($m['stream_url']) ?>" target="_blank" rel="noopener">
                        Смотреть трансляцию
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (Auth::can('mark_attendance')): ?>
            <div class="card shadow-sm mb-4" id="attendance">
                <div class="card-header fw-semibold">Моя отметка</div>
                <div class="card-body">
                    <?php $me = $myAttendance; ?>
                    <form method="post" action="<?= url('attendance_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach (ATTENDANCE_STATUSES as $value => $label): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="st-<?= $value ?>"
                                                   value="<?= $value ?>"
                                                   <?= $me && $me['status'] === $value ? 'checked' : '' ?>
                                                   <?= $value === 'present' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="st-<?= $value ?>"><?= e($label) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="attendance_reason">Причина (если онлайн или отсутствие)</label>
                                <textarea class="form-control" id="attendance_reason" name="reason" rows="2"><?= e($me['reason'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Сохранить отметку</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('add_expense')): ?>
            <div class="card shadow-sm mb-4" id="expenses">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Мои расходы по встрече</span>
                    <?php if (Auth::can('view_expenses')): ?>
                        <span class="badge text-bg-light border">итого: <?= money($expensesTotal) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (Auth::can('view_expenses')): ?>
                        <?php if ($expenses): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th>Кто</th>
                                        <th>Сумма</th>
                                        <th>Описание</th>
                                        <th class="text-end">Дата</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($expenses as $exp): ?>
                                        <tr>
                                            <td><?= e(full_name($exp)) ?></td>
                                            <td class="fw-semibold"><?= money($exp['amount']) ?></td>
                                            <td><?= e($exp['description']) ?></td>
                                            <td class="text-body-secondary small text-end"><?= e(date('d.m.Y', strtotime($exp['created_at']))) ?></td>
                                            <td class="text-end">
                                                <?php if ((int)$exp['user_id'] === Auth::id() || Auth::can('view_expenses')): ?>
                                                    <form method="post" action="<?= url('expense_delete') ?>"
                                                          onsubmit="return confirm('Удалить запись о расходе?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-body-secondary small">Записей о расходах пока нет.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="post" action="<?= url('expense_add') ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                        <div class="col-md-3">
                            <input class="form-control" type="text" name="amount" placeholder="Сумма, ₽" required inputmode="decimal">
                        </div>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="description" placeholder="На что потрачено">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-outline-primary" type="submit">Записать расход</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if ($rolesByRole): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Роли на встречу</div>
                <div class="card-body">
                    <?php foreach (MEETING_ROLES as $roleName): ?>
                        <?php if (empty($rolesByRole[$roleName])): continue; endif; ?>
                        <div class="mb-2">
                            <div class="small fw-semibold text-primary"><?= e($roleName) ?></div>
                            <div class="small"><?= e(implode(', ', array_map(fn($p) => full_name($p), $rolesByRole[$roleName]))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('view_attendance')): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Явка участников</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Участник</th>
                                <th>Статус</th>
                                <th>Причина</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$attendance): ?>
                                <tr><td colspan="3" class="text-body-secondary small">Отметок пока нет</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td class="small"><?= e(full_name($a)) ?></td>
                                    <td><span class="badge <?= attendance_badge_class($a['status']) ?>"><?= ATTENDANCE_STATUSES[$a['status']] ?? '' ?></span></td>
                                    <td class="small text-body-secondary"><?= e($a['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Распределить роли</div>
                <div class="card-body">
                    <form method="post" action="<?= url('roles_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2 roles-matrix">
                                <thead>
                                <tr>
                                    <th>Участник</th>
                                    <?php foreach (MEETING_ROLES as $roleName): ?>
                                        <th class="text-center small" data-bs-toggle="tooltip" title="<?= e($roleName) ?>">
                                            <span class="d-none d-xl-inline"><?= e($roleName) ?></span>
                                            <span class="d-xl-none"><?= e(short_role($roleName)) ?></span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($activeUsers as $u): ?>
                                    <tr>
                                        <td class="small"><?= e(user_short($u)) ?></td>
                                        <?php foreach (MEETING_ROLES as $roleName): ?>
                                            <td class="text-center">
                                                <input class="form-check-input" type="checkbox"
                                                       name="roles[<?= (int)$u['id'] ?>][]" value="<?= e($roleName) ?>"
                                                       <?= !empty($assigned[$u['id']][$roleName]) ? 'checked' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Сохранить роли</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>