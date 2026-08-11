<?php
if (empty($roles)) {
    return;
}
$byRole = [];
foreach ($roles as $r) {
    $byRole[$r['role']][] = $r;
}
?>
<div class="mt-2">
    <div class="fw-semibold small text-body-secondary">Роли на встречу</div>
    <div class="d-flex flex-wrap gap-2 mt-1">
        <?php foreach (MEETING_ROLES as $roleName): ?>
            <?php if (empty($byRole[$roleName])): continue; endif; ?>
            <div class="border rounded-3 p-2 bg-body-tertiary">
                <div class="small fw-semibold text-primary"><?= e($roleName) ?></div>
                <?php foreach ($byRole[$roleName] as $person): ?>
                    <div class="small">
                        <?= e(full_name($person)) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>