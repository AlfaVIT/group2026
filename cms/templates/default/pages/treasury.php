<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Казна</h1>
        <p class="text-body-secondary small mb-0">Поступления: регулярные взносы, десятины, прочее</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php foreach (TREASURY_PURPOSES as $p): ?>
            <span class="badge text-bg-light border">
                <?= e($p) ?>: <strong><?= money($totals[$p] ?? 0) ?></strong>
            </span>
        <?php endforeach; ?>
        <span class="badge text-bg-dark">Итого: <strong><?= money(array_sum($totals)) ?></strong></span>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Записать поступление</div>
            <div class="card-body">
                <form method="post" action="<?= url('treasury_save') ?>" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12">
                        <label class="form-label" for="t_date">Когда</label>
                        <input class="form-control" type="date" id="t_date" name="date" required
                               value="<?= today() ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="t_from">От кого</label>
                        <select class="form-select" id="t_from" name="from_user_id">
                            <option value="">— выберите участника —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= e(full_name($u)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">или укажите вручную:</div>
                        <input class="form-control mt-1" type="text" id="t_from_text" name="from_text" placeholder="От кого (если нет в списке)">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="t_purpose">За что</label>
                        <select class="form-select" id="t_purpose" name="purpose" required>
                            <?php foreach (TREASURY_PURPOSES as $p): ?>
                                <option value="<?= e($p) ?>"><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="t_amount">Сумма, ₽</label>
                        <input class="form-control" type="text" id="t_amount" name="amount" required inputmode="decimal">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="t_note">Примечание</label>
                        <input class="form-control" type="text" id="t_note" name="note">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary w-100" type="submit">Записать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Поступления</div>
            <div class="card-body">
                <form method="get" action="<?= url('treasury') ?>" class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <select class="form-select form-select-sm" name="purpose">
                            <option value="">Все назначения</option>
                            <?php foreach (TREASURY_PURPOSES as $p): ?>
                                <option value="<?= e($p) ?>" <?= $filters['purpose'] === $p ? 'selected' : '' ?>>
                                    <?= e($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1" type="submit">Фильтр</button>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury') ?>">Сброс</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th>От кого</th>
                            <th>За что</th>
                            <th class="text-end">Сумма</th>
                            <th>Примечание</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$entries): ?>
                            <tr><td colspan="6" class="text-body-secondary small">Записей нет</td></tr>
                        <?php endif; ?>
                        <?php foreach ($entries as $en): ?>
                            <tr>
                                <td class="small"><?= e(date('d.m.Y', strtotime($en['date']))) ?></td>
                                <td class="small"><?= e($en['from_name'] ? full_name(['address_term' => $en['from_term'], 'name' => $en['from_name']]) : $en['from_text']) ?></td>
                                <td><span class="badge text-bg-light border"><?= e($en['purpose']) ?></span></td>
                                <td class="text-end fw-semibold"><?= money($en['amount']) ?></td>
                                <td class="small text-body-secondary"><?= e($en['note']) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= url('treasury_delete') ?>"
                                          onsubmit="return confirm('Удалить запись?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$en['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>