<?php
$suggestUrl = $suggestUrl ?? url('article_suggest');
$byKind = ['income' => [], 'expense' => []];
foreach ($articles as $a) {
    $byKind[$a['kind']][] = $a;
}
$counts = ['income' => count($byKind['income']), 'expense' => count($byKind['expense'])];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Статьи казны</h1>
        <p class="text-body-secondary small mb-0">
            Справочник статей для поступлений и расходов. Похожие названия можно объединить —
            записи перейдут на выбранную статью.
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::can('view_treasury') || Auth::can('view_treasury_graph')): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('treasury') ?>">← Казна</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($pairs): ?>
    <div class="card shadow-sm mb-4 border-warning">
        <div class="card-header fw-semibold">Похожие статьи (<?= count($pairs) ?>)</div>
        <div class="card-body">
            <p class="text-body-secondary small">
                Эти названия отличаются написанием или очень близки. Объедините их, если это одна
                статья, или отметьте как разные — тогда пара больше не будет предлагаться.
            </p>
            <?php foreach ($pairs as $p): ?>
                <?php
                $a = $p['a'];
                $b = $p['b'];
                $label = $kindLabels[$p['kind']] ?? $p['kind'];
                ?>
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small">
                            <span class="badge text-bg-light border me-1"><?= e($label) ?></span>
                            «<?= e($a['name']) ?>» и «<?= e($b['name']) ?>»
                        </div>
                        <span class="badge text-bg-warning">совпадение <?= (int)$p['score'] ?>%</span>
                    </div>
                    <div class="text-body-secondary small mt-1">
                        «<?= e($a['name']) ?>»: <?= (int)$a['usage']['count'] ?> зап. на <?= money($a['usage']['sum']) ?>
                        &nbsp;·&nbsp;
                        «<?= e($b['name']) ?>»: <?= (int)$b['usage']['count'] ?> зап. на <?= money($b['usage']['sum']) ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <form method="post" action="<?= url('article_merge') ?>" class="d-inline" data-no-ajax
                              onsubmit="return confirm('Объединить статьи? Все записи перейдут на выбранную.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="from" value="<?= (int)$b['id'] ?>">
                            <input type="hidden" name="to" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                Объединить в «<?= e($a['name']) ?>»
                            </button>
                        </form>
                        <form method="post" action="<?= url('article_merge') ?>" class="d-inline" data-no-ajax
                              onsubmit="return confirm('Объединить статьи? Все записи перейдут на выбранную.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="from" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="to" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                Объединить в «<?= e($b['name']) ?>»
                            </button>
                        </form>
                        <form method="post" action="<?= url('article_dismiss') ?>" class="d-inline" data-no-ajax>
                            <?= csrf_field() ?>
                            <input type="hidden" name="kind" value="<?= e($p['kind']) ?>">
                            <input type="hidden" name="key_a" value="<?= e($a['key']) ?>">
                            <input type="hidden" name="key_b" value="<?= e($b['key']) ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Это разные статьи</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Добавить статью</div>
    <div class="card-body">
        <form method="post" action="<?= url('article_save') ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small" for="art_kind">Вид</label>
                <select class="form-select" id="art_kind" name="kind">
                    <?php foreach ($kindLabels as $kindKey => $kindLabel): ?>
                        <option value="<?= e($kindKey) ?>"><?= e($kindLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="art_name">Название</label>
                <input class="form-control" type="text" id="art_name" name="name" required maxlength="100"
                       autocomplete="off"
                       data-article-suggest="income"
                       data-suggest-kind-source="art_kind"
                       data-suggest-url="<?= e($suggestUrl) ?>"
                       placeholder="Например: Трапеза">
                <div class="article-suggest small mt-1" data-for="art_name"></div>
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-primary" type="submit">Добавить</button>
            </div>
        </form>
        <div class="form-text mt-2">
            Если название похоже на существующее, появится подсказка — лучше выбрать её,
            чтобы не заводить дубль.
        </div>
    </div>
</div>

<?php foreach ($kindLabels as $kindKey => $kindLabel): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span><?= e($kindLabel) ?></span>
            <span class="small text-body-secondary"><?= (int)$counts[$kindKey] ?> шт.</span>
        </div>
        <div class="card-body">
            <?php if (!$byKind[$kindKey]): ?>
                <p class="text-body-secondary small mb-0">Пока нет статей этого вида.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th class="text-end">Записей</th>
                            <th class="text-end">Сумма</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($byKind[$kindKey] as $a): ?>
                            <?php
                            $id = (int)$a['id'];
                            $u = $usage[$id] ?? ['count' => 0, 'sum' => 0.0];
                            $archived = (int)$a['archived'] === 1;
                            ?>
                            <tr class="<?= $archived ? 'text-body-secondary' : '' ?>">
                                <td>
                                    <form method="post" action="<?= url('article_update') ?>"
                                          class="d-flex flex-wrap gap-1 align-items-center" data-no-ajax>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input class="form-control form-control-sm" style="max-width: 280px;"
                                               type="text" name="name" value="<?= e($a['name']) ?>" required
                                               maxlength="100" autocomplete="off"
                                               id="art-name-<?= $id ?>"
                                               data-article-suggest="<?= e($kindKey) ?>"
                                               data-suggest-url="<?= e($suggestUrl) ?>">
                                        <?php if ($archived): ?>
                                            <span class="badge text-bg-secondary">скрыта</span>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-primary" type="submit"
                                                name="do" value="rename">Сохранить</button>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"
                                                name="do" value="<?= $archived ? 'restore' : 'archive' ?>">
                                            <?= $archived ? 'Вернуть' : 'Скрыть' ?>
                                        </button>
                                        <?php if ((int)$u['count'] === 0): ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                                    name="do" value="delete"
                                                    onclick="return confirm('Удалить статью?');">
                                                Удалить
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <div class="article-suggest small" data-for="art-name-<?= $id ?>"></div>
                                </td>
                                <td class="text-end"><?= (int)$u['count'] ?></td>
                                <td class="text-end"><?= money($u['sum']) ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
