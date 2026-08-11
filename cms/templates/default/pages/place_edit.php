<?php
$isEdit = (bool)$place;
$mapsPreview = !empty($data['geo_link']) ? $data['geo_link'] : null;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= e($title) ?></h1>
    <?php if ($isEdit): ?>
        <a class="btn btn-outline-secondary" href="<?= url('settings', ['tab' => 'places']) ?>">К списку мест</a>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('place_save') ?>" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$place['id'] ?>">
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label" for="title">Наименование</label>
        <input class="form-control" type="text" id="title" name="title" required
               value="<?= e($data['title'] ?? '') ?>" placeholder="Например: Дом у брата Сергея">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="owner_id">Хозяин</label>
        <select class="form-select" id="owner_id" name="owner_id">
            <option value="">— не выбран —</option>
            <?php foreach ($owners as $o): ?>
                <option value="<?= (int)$o['id'] ?>"
                    <?= (int)($data['owner_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>>
                    <?= e(full_name($o)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="region">Область</label>
        <input class="form-control" type="text" id="region" name="region" value="<?= e($data['region'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="district">Район</label>
        <input class="form-control" type="text" id="district" name="district" value="<?= e($data['district'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="locality">Населённый пункт</label>
        <input class="form-control" type="text" id="locality" name="locality" value="<?= e($data['locality'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label class="form-label" for="street">Улица</label>
        <input class="form-control" type="text" id="street" name="street" value="<?= e($data['street'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="house">Номер дома</label>
        <input class="form-control" type="text" id="house" name="house" value="<?= e($data['house'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="house_letter">Литера</label>
        <input class="form-control" type="text" id="house_letter" name="house_letter"
               value="<?= e($data['house_letter'] ?? '') ?>" placeholder="А">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="entrance">Подъезд</label>
        <input class="form-control" type="text" id="entrance" name="entrance" value="<?= e($data['entrance'] ?? '') ?>">
    </div>
    <div class="col-md-1">
        <label class="form-label" for="floor">Этаж</label>
        <input class="form-control" type="text" id="floor" name="floor" value="<?= e($data['floor'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="apartment">Квартира</label>
        <input class="form-control" type="text" id="apartment" name="apartment" value="<?= e($data['apartment'] ?? '') ?>">
    </div>
    <div class="col-md-1">
        <label class="form-label" for="intercom">Домофон</label>
        <input class="form-control" type="text" id="intercom" name="intercom" value="<?= e($data['intercom'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="geo_link">Геолокация (ссылка на Яндекс.Карты)</label>
        <input class="form-control" type="url" id="geo_link" name="geo_link" value="<?= e($data['geo_link'] ?? '') ?>"
               placeholder="https://yandex.ru/maps/…">
    </div>
    <div class="col-md-6 align-self-end">
        <button type="button" class="btn btn-outline-info btn-sm" id="geoGenerate">Собрать ссылку из адреса</button>
        <?php if ($mapsPreview): ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= e($mapsPreview) ?>" target="_blank" rel="noopener">Проверить ссылку</a>
        <?php endif; ?>
    </div>

    <div class="col-12">
        <label class="form-label" for="note">Примечание</label>
        <textarea class="form-control" id="note" name="note" rows="2"><?= e($data['note'] ?? '') ?></textarea>
    </div>

    <div class="col-12">
        <label class="form-label">Фотографии</label>
        <input class="form-control" type="file" name="photos[]" id="photos" multiple accept="image/jpeg,image/png,image/gif,image/webp">
        <div class="form-text">Можно выбрать несколько изображений (до 10 МБ каждое).</div>
        <?php if ($photos): ?>
            <div class="row row-cols-2 row-cols-md-4 g-3 mt-1">
                <?php foreach ($photos as $ph): ?>
                    <div class="col">
                        <div class="position-relative">
                            <img src="<?= base_url() ?>/<?= e(UPLOAD_URL) ?>/<?= e($ph['file_path']) ?>"
                                 class="img-fluid rounded border" alt="Фото места">
                            <form method="post" action="<?= url('photo_delete') ?>" class="position-absolute top-0 end-0 m-1"
                                  onsubmit="return confirm('Удалить фотографию?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$ph['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit" title="Удалить">&times;</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-outline-secondary" href="<?= url('settings', ['tab' => 'places']) ?>">Отмена</a>
    </div>
</form>