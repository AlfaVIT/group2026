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
                <li class="mb-1">
                    <a href="<?= e($item['url'] ?? '#') ?>" target="_blank" rel="noopener"
                       class="text-decoration-none">
                        <span class="badge bg-primary-subtle text-primary-emphasis me-1">ссылка</span>
                        <?= e($item['title'] ?? $item['url']) ?>
                    </a>
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