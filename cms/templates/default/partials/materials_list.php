<?php
$materials = decode_materials($meeting['materials'] ?? null);
if (!$materials) {
    return;
}
?>
<div class="mt-2">
    <div class="fw-semibold small text-body-secondary">Материалы для подготовки</div>
    <ul class="list-unstyled small mb-0 mt-1">
        <?php foreach ($materials as $item): ?>
            <?php if (($item['type'] ?? '') === 'link'): ?>
                <?php $hasTitle = !empty($item['title']) && $item['title'] !== ($item['url'] ?? ''); ?>
                <li class="mb-1 d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-primary-subtle text-primary-emphasis">ссылка</span>
                    <?php if ($hasTitle): ?>
                        <a href="<?= e($item['url'] ?? '#') ?>" target="_blank" rel="noopener"
                           class="text-decoration-none fw-semibold"><?= e($item['title']) ?></a>
                    <?php else: ?>
                        <a href="<?= e($item['url'] ?? '#') ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-primary py-0">Перейти</a>
                    <?php endif; ?>
                </li>
            <?php else: ?>
                <li class="mb-1">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">текст</span>
                    <?php if (!empty($item['title'])): ?>
                        <span class="fw-semibold"><?= e($item['title']) ?>: </span>
                    <?php endif; ?>
                    <span class="text-body-emphasis"><?= nl2br(e($item['text'] ?? '')) ?></span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div>