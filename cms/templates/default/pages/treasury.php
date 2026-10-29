<?php
$isTreasurer = Auth::can('view_treasury');
$canViewState = Auth::can('view_treasury') || Auth::can('view_treasury_graph');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Казна</h1>
        <p class="text-body-secondary small mb-0">Состояние казны: поступления и расходы группы</p>
    </div>
    <?php if ($isTreasurer): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('treasury_report') ?>">Консолидированный отчёт</a>
    <?php endif; ?>
</div>

<?php if ($canViewState): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Состояние казны</span>
        <span class="small text-body-secondary">Динамика баланса по статьям</span>
    </div>
    <div class="card-body">
        <?php partial('treasury_chart', ['chart' => $chart]); ?>
        <hr class="my-3">
        <div class="fw-semibold small mb-2">Состояние по статьям</div>
        <div class="table-responsive" id="articleStats">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Статья</th>
                    <th class="text-end">Поступления</th>
                    <th class="text-end">Расходы</th>
                    <th class="text-end">Баланс</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$articleStats): ?>
                    <tr><td colspan="4" class="text-body-secondary small">Записей пока нет</td></tr>
                <?php endif; ?>
                <?php foreach ($articleStats as $a => $st): ?>
                    <tr>
                        <td class="small fw-semibold"><?= e($a) ?></td>
                        <td class="text-end text-success">+<?= money($st['income']) ?></td>
                        <td class="text-end text-danger">-<?= money($st['expense']) ?></td>
                        <td class="text-end fw-semibold <?= $st['balance'] < 0 ? 'text-danger' : ($st['balance'] > 0 ? 'text-success' : '') ?>">
                            <?= money($st['balance']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light">
                    <td class="small fw-semibold">Итого</td>
                    <td class="text-end text-success">+<?= money($incomeTotal) ?></td>
                    <td class="text-end text-danger">-<?= money($expenseTotal) ?></td>
                    <td class="text-end fw-semibold"><?= money($balanceTotal) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isTreasurer): ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Поступления</span>
                    <span class="badge text-bg-light border" id="incomeTotalBadge">итого: <?= money($incomeEntriesTotal) ?></span>
                </div>
                <div class="card-body">
                    <button class="btn btn-sm btn-outline-primary mb-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#addIncomeCard"
                            aria-expanded="false" aria-controls="addIncomeCard">Добавить</button>
                    <div class="collapse mb-3" id="addIncomeCard">
                        <form method="post" action="<?= url('treasury_save') ?>" class="row g-3" data-ajax-update="#articleStats,#entriesTable,#incomeTotalBadge">
                            <?= csrf_field() ?>

                            <div class="col-12">
                                <label class="form-label" for="t_date">Дата</label>
                                <input class="form-control" type="date" id="t_date" name="date" required
                                       value="<?= today() ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="t_period">Период</label>
                                <input class="form-control" type="date" id="t_period" name="period" value="">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="t_from">От кого</label>
                                <input class="form-control" type="text" id="t_from" name="from" list="fromList"
                                       placeholder="Выберите участника или введите вручную" autocomplete="off">
                                <datalist id="fromList">
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= e(full_name($u)) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="t_amount">Сумма, ₽</label>
                                <input class="form-control" type="text" id="t_amount" name="amount" required inputmode="decimal">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="t_purpose">Статья</label>
                                <input class="form-control" type="text" id="t_purpose" name="purpose" list="purposeList" required
                                       placeholder="Выберите или введите новое значение">
                                <datalist id="purposeList">
                                    <?php foreach ($purposes as $p): ?>
                                        <option value="<?= e($p) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="t_note">Примечание</label>
                                <input class="form-control" type="text" id="t_note" name="note">
                            </div>

                            <div class="col-12">
                                <button class="btn btn-primary w-100" type="submit">Записать поступление</button>
                            </div>
                        </form>
                    </div>

                    <hr class="my-3">

                        <form method="get" action="<?= url('treasury') ?>" class="row g-2 mb-3">
                            <input type="hidden" name="p" value="treasury">
                            <div class="col-6 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">от</span>
                                    <input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">по</span>
                                    <input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <select class="form-select form-select-sm" name="purpose">
                                    <option value="">Статья</option>
                                    <?php foreach ($purposes as $t): ?>
                                        <option value="<?= e($t) ?>" <?= ($filters['purpose'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3 d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" type="submit">Фильтр</button>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury') ?>">Сброс</a>
                            </div>
                        </form>

                        <div class="table-responsive" id="entriesTable">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>От кого</th>
                                    <th>Статья</th>
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
                                        <td class="text-end fw-semibold text-success">+<?= money($en['amount']) ?></td>
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

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Расходы</span>
                    <span class="badge text-bg-light border" id="expensesTotalBadge">итого: <?= money($expensesTotal) ?></span>
                </div>
                <div class="card-body">
                    <button class="btn btn-sm btn-outline-primary mb-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#addExpenseCard"
                            aria-expanded="false" aria-controls="addExpenseCard">Добавить</button>
                    <div class="collapse mb-3" id="addExpenseCard">
                        <form method="post" action="<?= url('expense_t_edit') ?>" class="row g-3" data-ajax-update="#articleStats,#expensesTable,#expensesTotalBadge">
                            <?= csrf_field() ?>

                            <div class="col-12">
                                <label class="form-label" for="te_date">Дата</label>
                                <input class="form-control" type="date" id="te_date" name="date" required
                                       value="<?= today() ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="te_period">Период</label>
                                <input class="form-control" type="date" id="te_period" name="period" value="">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="te_to">Кому</label>
                                <input class="form-control" type="text" id="te_to" name="to" list="toList"
                                       placeholder="Выберите участника или введите вручную" autocomplete="off">
                                <datalist id="toList">
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= e(full_name($u)) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="te_amount">Сумма, ₽</label>
                                <input class="form-control" type="text" id="te_amount" name="amount" required inputmode="decimal">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="te_type">Статья</label>
                                <select class="form-select" id="te_type" name="type" required>
                                    <option value="">— выберите статью —</option>
                                    <?php foreach ($expenseArticles as $t): ?>
                                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="te_description">Описание</label>
                                <textarea class="form-control" id="te_description" name="description" rows="2" required
                                          placeholder="На что потрачено"></textarea>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-primary w-100" type="submit">Записать расход</button>
                            </div>
                        </form>
                    </div>

                    <hr class="my-3">

                        <form method="get" action="<?= url('treasury') ?>" class="row g-2 mb-3">
                            <input type="hidden" name="p" value="treasury">
                            <div class="col-6 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">от</span>
                                    <input class="form-control" type="date" name="exp_from" value="<?= e($expFilters['from']) ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">по</span>
                                    <input class="form-control" type="date" name="exp_to" value="<?= e($expFilters['to']) ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <select class="form-select form-select-sm" name="exp_type">
                                    <option value="">Статья</option>
                                    <?php foreach ($expenseArticles as $t): ?>
                                        <option value="<?= e($t) ?>" <?= $expFilters['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3 d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" type="submit">Фильтр</button>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury') ?>">Сброс</a>
                            </div>
                        </form>

                        <div class="table-responsive" id="expensesTable">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Кому</th>
                                    <th>Статья</th>
                                    <th class="text-end">Сумма</th>
                                    <th>Описание</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$expenses): ?>
                                    <tr><td colspan="6" class="text-body-secondary small">Расходов пока нет</td></tr>
                                <?php endif; ?>
                                <?php foreach ($expenses as $ex): ?>
                                    <tr>
                                        <td class="small"><?= e(date('d.m.Y', strtotime($ex['date']))) ?></td>
                                        <td class="small"><?php
                                            if (!empty($ex['to_name'])) {
                                                echo e(trim(($ex['to_term'] ?? '') . ' ' . $ex['to_name']));
                                            } elseif (!empty($ex['to_text'])) {
                                                echo e($ex['to_text']);
                                            } else {
                                                echo '—';
                                            }
                                        ?></td>
                                        <td><span class="badge text-bg-light border"><?= e($ex['type']) ?></span></td>
                                        <td class="text-end fw-semibold text-danger">-<?= money($ex['amount']) ?></td>
                                        <td class="small text-body-secondary"><?= e($ex['description']) ?></td>
                                        <td class="text-end">
                                            <form method="post" action="<?= url('expense_t_del') ?>"
                                                  onsubmit="return confirm('Удалить расход?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
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

    <div class="card shadow-sm mt-4" id="meetingExpensesCard">
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            <span class="fw-semibold">Расходы участников по встречам</span>
            <span class="small text-body-secondary">Подробно, по встречам и участникам</span>
        </div>
        <div class="card-body">
            <?php if (!$meetingExpenses): ?>
                <p class="text-body-secondary small mb-0">Расходов по встречам пока нет.</p>
            <?php else: ?>
                <?php foreach ($meetingExpenses as $me): ?>
                    <div class="mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <strong class="small"><?= e(rdate($me['date'])) ?><?= $me['topic'] ? ' — ' . e($me['topic']) : '' ?></strong>
                            <span class="badge text-bg-light border">итого: <?= money($me['total']) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1">
                                <thead>
                                <tr>
                                    <th class="small">Участник</th>
                                    <th class="small">Расход</th>
                                    <th class="small text-end">Сумма</th>
                                    <th class="small text-end">Записан</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($me['users'] as $pu): ?>
                                    <tr class="table-light">
                                        <td class="small fw-semibold"><?= e($pu['name']) ?></td>
                                        <td colspan="2" class="small text-body-secondary"><?= count($pu['items']) ?> <?= plural_ru(count($pu['items']), 'запись', 'записи', 'записей') ?>, всего <?= money($pu['total']) ?></td>
                                        <td class="small text-end"></td>
                                    </tr>
                                    <?php foreach ($pu['items'] as $it): ?>
                                        <tr>
                                            <td class="small"></td>
                                            <td class="small"><?= e($it['description']) ?></td>
                                            <td class="small text-end text-danger">-<?= money($it['amount']) ?></td>
                                            <td class="small text-body-secondary text-end"><?= e(date('d.m.Y H:i', strtotime($it['created_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <p class="text-body-secondary">Записи казны ведёт Казначей. Формы поступлений и расходов доступны только казначею.</p>
<?php endif; ?>