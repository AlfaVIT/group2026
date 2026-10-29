<?php
$m = $meeting;
$address = address_parts($m, true);
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
    <h1 class="h3 mb-0 d-flex align-items-center gap-2 flex-wrap">
        <?= rdate($m['date']) ?>
        <?php if ($m['tag'] !== ''): ?>
            <span class="tag-badge"><?= e($m['tag']) ?></span>
        <?php endif; ?>
    </h1>
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
                <?php $cardTitle = $m['meeting_type'] !== '' ? $m['meeting_type'] : $m['topic']; ?>
                <?php if ($cardTitle !== ''): ?>
                    <h2 class="h5"><?= e($cardTitle) ?></h2>
                <?php endif; ?>

                <?php if ($m['place_id']): ?>
                    <div class="mb-2">
                        <?php if ($address): ?>
                            <div class="fw-semibold">Место проведения</div>
                            <div><?= e(implode(', ', $address)) ?></div>
                        <?php endif; ?>
                        <?php $widget = yandex_map_widget($m); ?>
                        <?php if ($widget !== ''): ?>
                            <div class="mt-2" style="max-width: 480px;">
                                <iframe src="<?= e($widget) ?>" class="cms-mini-map" loading="lazy"
                                        title="Карта" allowfullscreen></iframe>
                                <div class="small mt-1">
                                    <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Открыть карту</a>
                                </div>
                            </div>
                        <?php elseif ($maps !== ''): ?>
                            <a href="<?= e($maps) ?>" target="_blank" rel="noopener" class="text-decoration-none">Показать на карте</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mb-2">
                    <span class="text-body-secondary">Время начала:</span>
                    <span class="fw-semibold"><?= e(date('H:i', strtotime((string)$m['date']))) ?></span>
                </div>

                <?php if ($m['meeting_type'] !== '' && $m['topic'] !== ''): ?>
                    <div class="mt-2 small"><span class="text-body-secondary">Тема:</span> <?= e($m['topic']) ?></div>
                <?php endif; ?>

                <?php partial('materials_list', ['meeting' => $m]); ?>

                <?php if ($guestNames): ?>
                    <div class="mt-2"><span class="text-body-secondary">Гости:</span> <?= e(implode(', ', $guestNames)) ?></div>
                <?php endif; ?>

                <div class="mt-2">
                    <span class="text-body-secondary">Участников:</span> <?= (int)$participantsCount ?>
                </div>

                <?php if (($m['senior_name'] ?? '') !== ''): ?>
                    <div class="mt-2">
                        <span class="text-body-secondary">Старший на встрече:</span>
                        <?= e(full_name(['address_term' => $m['senior_term'] ?? 'брат', 'name' => $m['senior_name']])) ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-outline-primary" href="<?= url('vespers', ['meeting_id' => $m['id']]) ?>">
                        Вечерня братским чином
                    </a>
                    <?php if ($m['stream_url'] !== ''): ?>
                        <a class="btn btn-danger" href="<?= e($m['stream_url']) ?>" target="_blank" rel="noopener">
                            Смотреть трансляцию
                        </a>
                    <?php endif; ?>
                    <?php
                    $isAndroid = is_android_client();
                    $calHref = $isAndroid ? android_calendar_intent($m) : '';
                    if ($calHref === '') {
                        // iPhone и прочие — файл .ics (iOS открывает календарь, остальные скачивают)
                        $calHref = url('meeting_ics', ['id' => $m['id']]);
                    }
                    ?>
                    <a class="btn btn-outline-success" href="<?= e($calHref) ?>">
                        Добавить в календарь
                    </a>
                </div>
            </div>
        </div>

        <?php
        $clTotal = 0;
        $clDone = 0;
        foreach (MEETING_CHECKLIST as $clItems) {
            foreach (array_keys($clItems) as $clKey) {
                $clTotal++;
                if (!empty($checklist[$clKey]['checked'])) {
                    $clDone++;
                }
            }
        }
        ?>
        <div class="card shadow-sm mb-4" id="checklist">
            <div class="card-header p-0">
                <button class="btn btn-link d-flex flex-wrap justify-content-between align-items-center gap-2 w-100 text-start text-decoration-none py-2 px-3"
                        type="button" data-bs-toggle="collapse" data-bs-target="#checklistBody"
                        aria-expanded="false" aria-controls="checklistBody">
                    <span class="fw-semibold">Чеклист подготовки</span>
                    <span class="small text-body-secondary">Готово: <?= $clDone ?> из <?= $clTotal ?></span>
                </button>
            </div>
            <div class="collapse" id="checklistBody">
                <div class="card-body">
                <?php if (Auth::can('mark_attendance')): ?>
                    <form method="post" action="<?= url('checklist_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                        <?php foreach (MEETING_CHECKLIST as $clGroup => $clItems): ?>
                            <h6 class="fw-semibold text-primary mt-2 mb-2"><?= e($clGroup) ?></h6>
                            <?php foreach ($clItems as $clKey => $clLabel): ?>
                                <?php $cl = $checklist[$clKey] ?? ['checked' => 0, 'comment' => '']; ?>
                                <div class="row g-1 align-items-center mb-1">
                                    <div class="col-12 col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="items[<?= e($clKey) ?>]"
                                                   value="1" id="cl-<?= e($clKey) ?>" <?= $cl['checked'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="cl-<?= e($clKey) ?>"><?= e($clLabel) ?></label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <input class="form-control form-control-sm" type="text"
                                               name="comments[<?= e($clKey) ?>]" value="<?= e($cl['comment']) ?>"
                                               placeholder="Комментарий">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <button class="btn btn-primary mt-3" type="submit">Сохранить чеклист</button>
                    </form>
                <?php else: ?>
                    <?php foreach (MEETING_CHECKLIST as $clGroup => $clItems): ?>
                        <h6 class="fw-semibold text-primary mt-2 mb-2"><?= e($clGroup) ?></h6>
                        <?php foreach ($clItems as $clKey => $clLabel): ?>
                            <?php $cl = $checklist[$clKey] ?? ['checked' => 0, 'comment' => '']; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" disabled <?= $cl['checked'] ? 'checked' : '' ?>>
                                <label class="form-check-label">
                                    <?= e($clLabel) ?>
                                    <?php if ($cl['comment'] !== ''): ?>
                                        <span class="text-body-secondary small">— <?= e($cl['comment']) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($rolesByRole): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Роли на встречу</div>
                <div class="card-body">
                    <?php foreach (MEETING_ROLES as $roleName): ?>
                        <?php if (empty($rolesByRole[$roleName])): continue; endif; ?>
                        <div class="mb-2">
                            <div class="small fw-semibold text-primary"><?= e(role_label($roleName)) ?></div>
                            <div class="small"><?= e(implode(', ', array_map(fn($p) => full_name($p), $rolesByRole[$roleName]))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('view_attendance') || Auth::can('mark_attendance')): ?>
            <div class="card shadow-sm mb-4" id="attendance">
                <div class="card-header fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Явка участников</span>
                    <?php if (Auth::can('view_attendance')): ?>
                        <span class="small text-body-secondary">Очно: <?= (int)$presentCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (Auth::can('view_attendance')): ?>
                        <?php
                        $marked = array_values(array_filter($attendance, fn($a) => $a['status'] !== ''));
                        $unmarked = array_values(array_filter($attendance, fn($a) => $a['status'] === ''));
                        $stLabels = ['present' => 'Очно', 'online' => 'Онлайн', 'absent' => 'Отсутствует'];
                        ?>
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
                                <?php if (!$marked): ?>
                                    <tr><td colspan="3" class="text-body-secondary small">Пока никто не отметился</td></tr>
                                <?php endif; ?>
                                <?php foreach ($marked as $a): ?>
                                    <tr>
                                        <td class="small"><?= e(full_name($a)) ?></td>
                                        <td><span class="badge <?= attendance_badge_class($a['status']) ?>"><?= $stLabels[$a['status']] ?? '' ?></span></td>
                                        <td class="small text-body-secondary"><?= e($a['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($unmarked): ?>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#attendanceUnmarked"
                                        aria-expanded="false" aria-controls="attendanceUnmarked">
                                    Не отметились (<?= count($unmarked) ?>)
                                </button>
                                <div class="collapse mt-2" id="attendanceUnmarked">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                            <tr>
                                                <th>Участник</th>
                                                <th>Статус</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($unmarked as $a): ?>
                                                <tr>
                                                    <td class="small"><?= e(full_name($a)) ?></td>
                                                    <td><span class="badge text-bg-secondary">Не отметился</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (Auth::can('mark_attendance')): ?>
                        <hr>
                        <div class="fw-semibold small mb-2">Моя отметка</div>
                        <?php $me = $myAttendance; ?>
                        <form method="post" action="<?= url('attendance_save') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                            <?php if (Auth::can('view_attendance')): ?>
                                <div class="mb-3">
                                    <label class="form-label small mb-1" for="att_user">Участник</label>
                                    <select class="form-select form-select-sm" id="att_user" name="user_id">
                                        <?php foreach ($attendance as $au): ?>
                                            <option value="<?= (int)$au['user_id'] ?>"
                                                    data-status="<?= e(($au['status'] ?? '') !== '' ? $au['status'] : 'present') ?>"
                                                    data-reason="<?= e($au['reason'] ?? '') ?>"
                                                    <?= (int)$au['user_id'] === (int)Auth::id() ? 'selected' : '' ?>>
                                                <?= e(full_name($au)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach (ATTENDANCE_STATUSES as $value => $label): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="st-<?= $value ?>"
                                                       value="<?= $value ?>"
                                                        <?= ($me['status'] ?: 'present') === $value ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="st-<?= $value ?>"><?= e($label) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="attendance_reason">
                                        Причина (обязательна при отсутствии)
                                    </label>
                                    <textarea class="form-control" id="attendance_reason" name="reason" rows="2"><?= e($me['reason'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">Сохранить отметку</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('view_attendance')): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header p-0">
                    <button class="btn btn-link w-100 text-start text-decoration-none py-2 px-3 fw-semibold"
                            type="button" data-bs-toggle="collapse" data-bs-target="#rolesBody"
                            aria-expanded="false" aria-controls="rolesBody">Распределить роли</button>
                </div>
                <div class="collapse" id="rolesBody">
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
                                        <th class="text-center small" data-bs-toggle="tooltip" title="<?= e(role_label($roleName)) ?>">
                                            <span class="d-none d-xl-inline"><?= e(role_label($roleName)) ?></span>
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
            </div>
        <?php endif; ?>

        <?php if (Auth::can('add_expense')): ?>
            <div class="card shadow-sm mb-4" id="expenses">
                <div class="card-header p-0">
                    <button class="btn btn-link d-flex flex-wrap justify-content-between align-items-center gap-2 w-100 text-start text-decoration-none py-2 px-3"
                            type="button" data-bs-toggle="collapse" data-bs-target="#expensesBody"
                            aria-expanded="false" aria-controls="expensesBody">
                        <span class="fw-semibold">Мои расходы по встрече</span>
                        <span class="badge text-bg-light border">итого: <?= money($expensesTotal) ?></span>
                    </button>
                </div>
                <div class="collapse" id="expensesBody">
                    <div class="card-body">
                        <?php if ($expenses): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th>Кто</th>
                                        <th>Сумма</th>
                                        <th>Статья</th>
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
                                            <td class="small"><?= e($exp['article'] ?? '') !== '' ? '<span class="badge text-bg-light border">' . e($exp['article']) . '</span>' : '—' ?></td>
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

                        <form method="post" action="<?= url('expense_add') ?>" class="row g-2" data-ajax-update="#expenses">
                            <?= csrf_field() ?>
                            <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                            <div class="col-md-3">
                                <input class="form-control" type="text" name="amount" placeholder="Сумма, ₽" required inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <input class="form-control" type="text" name="description" placeholder="На что потрачено">
                            </div>
                            <div class="col-md-3 d-grid">
                                <button class="btn btn-outline-primary" type="submit">Записать</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>