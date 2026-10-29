<?php
$m = $meeting;
$frameDate = date('Y-m-d', strtotime($m['date']));
$frameUrl = 'https://molitva.app/#/date/' . $frameDate . '/service/vespers';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 d-flex align-items-center gap-2 flex-wrap">
        Вечерня братским чином
        <?php if ($m['tag'] !== ''): ?>
            <span class="tag-badge"><?= e($m['tag']) ?></span>
        <?php endif; ?>
    </h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting', ['id' => $m['id']]) ?>">← К встрече</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <span class="text-body-secondary">Вечерня на день встречи:</span>
        <span class="fw-semibold"><?= rdate($m['date']) ?></span>
    </div>
</div>

<?php if ($vespersInline): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4" id="vespers-text-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Текст вечерни</span>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e($frameUrl) ?>" target="_blank" rel="noopener">
                        Открыть на molitva.app
                    </a>
                </div>
                <div class="card-body">
                    <div class="cms-vespers-text"><?= $vespersHtml ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <?php if ($slots && Auth::can('assign_readers')): ?>
                <div class="card shadow-sm mb-4" id="vespers-readers">
                    <div class="card-header fw-semibold">Чтецы</div>
                    <div class="card-body">
                        <form method="post" action="<?= url('vespers_readers_save') ?>" data-ajax-update="#vespers-readers,#vespers-text-card">
                            <?= csrf_field() ?>
                            <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
                            <?php foreach ($slots as $slot): ?>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold mb-1" for="vr-<?= e($slot['key']) ?>">
                                        <?= e($slot['label']) ?>
                                    </label>
                                    <select class="form-select form-select-sm" id="vr-<?= e($slot['key']) ?>"
                                            name="readers[<?= e($slot['key']) ?>]">
                                        <option value="">— не назначен —</option>
                                        <?php foreach ($presentUsers as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>"
                                                    <?= (int)($readerAssignments[$slot['key']] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                                <?= e(full_name($u)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                            <button class="btn btn-primary w-100" type="submit">Сохранить чтецов</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm mb-4" id="vespers-text-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">Текст вечерни</span>
            <a class="btn btn-sm btn-outline-primary" href="<?= e($frameUrl) ?>" target="_blank" rel="noopener">
                Открыть на molitva.app
            </a>
        </div>
        <div class="card-body">
            <iframe class="cms-vespers-frame" src="<?= e($frameUrl) ?>" title="Текст вечерни на molitva.app"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
<?php endif; ?>