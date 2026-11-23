<?php
$m = $meeting;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 d-flex align-items-center gap-2 flex-wrap">
        Литургия
        <?php if ($m['tag'] !== ''): ?>
            <span class="tag-badge"><?= e($m['tag']) ?></span>
        <?php endif; ?>
    </h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting', ['id' => $m['id']]) ?>">← К встрече</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <span class="text-body-secondary">Литургия на день встречи:</span>
        <span class="fw-semibold"><?= rdate($m['date']) ?></span>
    </div>
</div>

<div class="card shadow-sm mb-4" id="liturgy-text-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Текст Литургии</span>
        <a class="btn btn-sm btn-outline-primary" href="<?= e($frameUrl) ?>" target="_blank" rel="noopener">
            Открыть на molitva.app
        </a>
    </div>
    <div class="card-body">
        <p class="small text-body-secondary mb-3">
            Текст предоставляется приложением molitva.app и соответствует дате встречи
            (вид Литургии — Златоуста, Василия Великого или Преждеосвященные Дары — приложение
            выбирает по церковному календарю).
        </p>
        <iframe class="cms-vespers-frame" src="<?= e($frameUrl) ?>" title="Текст Литургии на molitva.app"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>
