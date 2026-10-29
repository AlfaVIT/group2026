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

    <div class="col-md-6">
        <label class="form-label" for="meeting_type">Тип встречи</label>
        <input class="form-control" type="text" id="meeting_type" name="meeting_type" list="meetingTypeList"
               value="<?= e($data['meeting_type'] ?? '') ?>" placeholder="Выберите или введите значение">
        <datalist id="meetingTypeList">
            <?php foreach ($meetingTypes as $mt): ?>
                <option value="<?= e($mt) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="topic">Тема</label>
        <input class="form-control" type="text" id="topic" name="topic" value="<?= e($data['topic'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="senior_user_id">Старший на встрече</label>
        <select class="form-select" id="senior_user_id" name="senior_user_id">
            <option value="">— не назначен —</option>
            <?php foreach ($seniorCandidates as $sc): ?>
                <option value="<?= (int)$sc['id'] ?>"
                    <?= (int)($data['senior_user_id'] ?? 0) === (int)$sc['id'] ? 'selected' : '' ?>>
                    <?= e(full_name($sc)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Выбирается из очных участников встречи (включая гостей); можно не указывать.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tag">Метка встречи</label>
        <input class="form-control" type="text" id="tag" name="tag" maxlength="50" list="tagList"
               value="<?= e($data['tag'] ?? '') ?>" placeholder="Например: катехизация, молитвенное собрание…">
        <datalist id="tagList">
            <?php foreach ($tags as $t): ?>
                <option value="<?= e($t['tag']) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <div class="form-text">Выберите из ранее использованных меток или введите новое значение</div>
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
        <select class="form-select" id="guests" name="guests[]" multiple size="5">
            <?php if (!$guestUsers): ?>
                <option value="" disabled>Нет учётных записей с ролью «Гость»</option>
            <?php endif; ?>
            <?php foreach ($guestUsers as $gu): ?>
                <option value="<?= (int)$gu['id'] ?>"
                    <?= in_array((string)$gu['id'], $guestIds, true) ? 'selected' : '' ?>>
                    <?= e(full_name($gu)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">
            Гости выбираются из учётных записей с ролью «Гость»; можно выбрать несколько
            (с зажатой Ctrl или Shift).
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="stream_url">Ссылка на трансляцию</label>
        <input class="form-control" type="url" id="stream_url" name="stream_url"
               value="<?= e($data['stream_url'] ?? '') ?>" placeholder="https://…">
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-outline-secondary" href="<?= $isEdit ? url('meeting', ['id' => $meeting['id']]) : url('home') ?>">Отмена</a>
    </div>
</form>