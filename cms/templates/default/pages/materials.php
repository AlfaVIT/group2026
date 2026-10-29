<?php
$page = $pageNum ?? 1;
$isEdit = $editMaterial !== null;
$form = $formData ?? ($editMaterial ?: []);
$fTitle = $form['title'] ?? '';
$fDescription = $form['description'] ?? '';
$fContent = $form['content'] ?? '';
$fTag = $form['tag'] ?? '';
$sortValue = $sortKey . '_' . $order;
$murl = function (array $extra = []) use ($q, $tag, $author, $sortValue, $page): string {
    $params = array_filter([
        'q' => $q, 'tag' => $tag, 'author' => $author ?: null, 'sort' => $sortValue, 'page' => $page > 1 ? $page : null,
    ]);
    return url('materials', array_merge($params, $extra));
};
$me = Auth::id();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Материалы</h1>
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addMaterialCard"
            aria-expanded="<?= $isEdit || $formData ? 'true' : 'false' ?>"
            aria-controls="addMaterialCard">
        <?= $isEdit ? 'Редактирование' : 'Разместить материал' ?>
    </button>
</div>

<div class="collapse <?= $isEdit || $formData ? 'show' : '' ?>" id="addMaterialCard">
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><?= $isEdit ? 'Редактирование материала' : 'Новый материал' ?></span>
            <?php if ($isEdit): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e($murl([])) ?>">Отмена</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" action="<?= url('material_save') ?>" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= (int)$editMaterial['id'] ?>">
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label" for="m_title">Название</label>
                    <input class="form-control" type="text" id="m_title" name="title" required maxlength="200"
                           value="<?= e($fTitle) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label" for="m_description">Описание</label>
                    <input class="form-control" type="text" id="m_description" name="description" maxlength="300"
                           placeholder="Коротко о материале"
                           value="<?= e($fDescription) ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="m_tag">Метка</label>
                    <input class="form-control" list="material_tags" id="m_tag" name="tag" maxlength="60"
                           placeholder="Выберите или введите новую метку"
                           value="<?= e($fTag) ?>">
                    <datalist id="material_tags">
                        <?php foreach ($tags as $t): ?>
                            <option value="<?= e($t) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Можно выбрать метку из ранее введённых или вписать новую</div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="m_content">Текст</label>
                    <textarea class="form-control" id="m_content" name="content" rows="6"
                              placeholder="Текст материала (или загрузите картинку)"><?= e($fContent) ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label" for="m_image">Картинка</label>
                    <?php if ($isEdit && $editMaterial['image_path']): ?>
                        <div class="mb-2">
                            <img src="<?= base_url() ?>/uploads/<?= e($editMaterial['image_path']) ?>"
                                 class="material-image material-image-sm" alt="">
                            <div class="form-text">Текущее изображение. Новый файл заменит его.</div>
                        </div>
                    <?php endif; ?>
                    <input class="form-control" type="file" id="m_image" name="image"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Сохранить изменения' : 'Разместить' ?></button>
                    <button class="btn btn-outline-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#addMaterialCard">Свернуть</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="get" action="<?= url('materials') ?>" class="card shadow-sm mb-4">
    <input type="hidden" name="p" value="materials">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-body-secondary mb-1" for="m_q">Поиск</label>
                <input class="form-control" type="search" id="m_q" name="q" value="<?= e($q) ?>"
                       placeholder="По названию и тексту">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-body-secondary mb-1" for="m_tag_filter">Метка</label>
                <select class="form-select" id="m_tag_filter" name="tag">
                    <option value="">— все —</option>
                    <?php foreach ($tags as $t): ?>
                        <option value="<?= e($t) ?>" <?= $tag === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-body-secondary mb-1" for="m_author">Автор</label>
                <select class="form-select" id="m_author" name="author">
                    <option value="">— все —</option>
                    <?php foreach ($authors as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $author === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e(full_name($a)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-body-secondary mb-1" for="m_sort">Сортировка</label>
                <select class="form-select" id="m_sort" name="sort">
                    <option value="date_desc" <?= $sortValue === 'date_desc' ? 'selected' : '' ?>>По дате: новые сверху</option>
                    <option value="date_asc" <?= $sortValue === 'date_asc' ? 'selected' : '' ?>>По дате: старые сверху</option>
                    <option value="title_asc" <?= $sortValue === 'title_asc' ? 'selected' : '' ?>>По названию (А–Я)</option>
                    <option value="title_desc" <?= $sortValue === 'title_desc' ? 'selected' : '' ?>>По названию (Я–А)</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit">Найти</button>
                <a class="btn btn-outline-secondary" href="<?= url('materials') ?>" title="Сбросить фильтры">✕</a>
            </div>
        </div>
    </div>
</form>

<?php if (!$materials): ?>
    <div class="alert alert-light border text-body-secondary">Материалов не найдено.</div>
<?php else: ?>
    <?php foreach ($materials as $m): ?>
        <article class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="h5 mb-1"><?= e($m['title']) ?></h2>
                        <div class="small text-body-secondary mb-2">
                            <?= e(full_name(['address_term' => $m['author_term'], 'name' => $m['author_name']])) ?>
                            · <?= e(rdate(substr($m['created_at'], 0, 10))) ?>
                            <?= e(date('H:i', strtotime($m['created_at']))) ?>
                            <?php if ($m['updated_at']): ?>
                                · Изменено: <?= e(full_name(['address_term' => $m['editor_term'] ?: 'брат', 'name' => $m['editor_name'] ?: '—'])) ?>
                                <?= e(date('d.m.Y H:i', strtotime($m['updated_at']))) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($m['tag']): ?>
                        <span class="tag-badge"><?= e($m['tag']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($m['description']): ?>
                    <p class="text-body-secondary mb-2"><?= e($m['description']) ?></p>
                <?php endif; ?>

                <?php if ($m['image_path']): ?>
                    <img src="<?= base_url() ?>/uploads/<?= e($m['image_path']) ?>"
                         class="material-image mb-2" alt="<?= e($m['title']) ?>">
                <?php endif; ?>

                <?php if ($m['content']): ?>
                    <div class="material-content"><?= nl2br(e($m['content'])) ?></div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e($murl(['edit' => $m['id']])) ?>">Редактировать</a>
                    <?php if (Auth::can('manage_materials') || (int)$m['user_id'] === $me): ?>
                        <form method="post" action="<?= url('material_delete') ?>" class="d-inline"
                              onsubmit="return confirm('Удалить материал?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>

    <div class="small text-body-secondary text-center mb-3">
        Показано: <?= count($materials) ?> из <?= (int)$total ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Страницы материалов">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($murl(['page' => $page - 1])) ?>">‹ Назад</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($murl(['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($murl(['page' => $page + 1])) ?>">Вперёд ›</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>