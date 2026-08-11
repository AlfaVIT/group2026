<?php
$isEdit = (bool)$meeting;
?>
<div class="mb-3">
    <h1 class="h3 mb-0"><?= e($title) ?></h1>
</div>

<form method="post" action="<?= url('meeting_save') ?>" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>">
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label" for="date">Дата и время проведения</label>
        <input class="form-control" type="datetime-local" id="date" name="date" required
               value="<?= e($data['date']) ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="place_id">Место проведения</label>
        <select class="form-select" id="place_id" name="place_id">
            <option value="">— не выбрано —</option>
            <?php foreach ($places as $p): ?>
                <option value="<?= (int)$p['id'] ?>"
                    <?= (int)($data['place_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label" for="topic">Тема</label>
        <input class="form-control" type="text" id="topic" name="topic" value="<?= e($data['topic'] ?? '') ?>">
    </div>

    <div class="col-12">
        <label class="form-label">Материалы для подготовки (ссылки и тексты)</label>
        <div id="materialsRows">
            <?php foreach ($materials as $i => $item): ?>
                <div class="materials-row border rounded-3 p-2 mb-2 bg-body-tertiary">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-md-3">
                            <select class="form-select form-select-sm mat-type" name="mat_type[]">
                                <option value="link" <?= ($item['type'] ?? '') === 'link' ? 'selected' : '' ?>>Ссылка</option>
                                <option value="text" <?= ($item['type'] ?? '') === 'text' ? 'selected' : '' ?>>Текст</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <input class="form-control form-control-sm mat-title" type="text" name="mat_title[]"
                                   placeholder="Название (необязательно)" value="<?= e($item['title'] ?? '') ?>">
                        </div>
                        <div class="col-10 col-md-4">
                            <input class="form-control form-control-sm mat-link d-block" type="url" name="mat_url[]"
                                   placeholder="https://…" value="<?= e(($item['type'] ?? '') === 'link' ? ($item['url'] ?? '') : '') ?>">
                            <textarea class="form-control form-control-sm mat-text d-none" name="mat_text[]"
                                      rows="2" placeholder="Текст материала"><?= e(($item['type'] ?? '') === 'text' ? ($item['text'] ?? '') : '') ?></textarea>
                        </div>
                        <div class="col-2 col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger mat-remove" title="Удалить">&times;</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="matAdd">Добавить материал</button>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="guests">Гости</label>
        <input class="form-control" type="text" id="guests" name="guests" value="<?= e($data['guests'] ?? '') ?>"
               placeholder="Имена гостей">
    </div>

    <div class="col-md-3">
        <label class="form-label" for="participants_count">Количество участников</label>
        <input class="form-control" type="number" id="participants_count" name="participants_count" min="0"
               value="<?= e($data['participants_count'] ?? '') ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label" for="stream_url">Ссылка на трансляцию</label>
        <input class="form-control" type="url" id="stream_url" name="stream_url"
               value="<?= e($data['stream_url'] ?? '') ?>" placeholder="https://…">
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-outline-secondary" href="<?= $isEdit ? url('meeting', ['id' => $meeting['id']]) : url('home') ?>">Отмена</a>
    </div>
</form>