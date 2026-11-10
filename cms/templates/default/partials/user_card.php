<?php
/**
 * Карточка участника: крупный аватар (или заглушка с инициалами), имя, роль,
 * контакты и дата рождения. Используется в модальном окне и на отдельной странице.
 */
$cardUser = $cardUser ?? [];
$cardAvatar = $cardAvatar ?? '';
$cardPhone = $cardPhone ?? '';
$cardEmail = $cardEmail ?? '';
$cardTelegram = $cardTelegram ?? '';
$cardTelegramLink = $cardTelegramLink ?? '';
$cardBirthday = $cardBirthday ?? '';
$cardAge = $cardAge ?? null;
$cardRole = $cardRole ?? '';
$cardPdNote = $cardPdNote ?? '';

$initials = '';
foreach (array_slice(preg_split('/\s+/u', trim((string)($cardUser['name'] ?? ''))) ?: [], 0, 2) as $word) {
    if ($word !== '') {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
}
$hasInfo = $cardPhone !== '' || $cardEmail !== '' || $cardTelegram !== '' || $cardBirthday !== '';
?>
<div class="cms-user-card">
    <?php if ($cardAvatar !== ''): ?>
        <img class="cms-user-card-avatar" src="<?= e($cardAvatar) ?>"
             alt="<?= e(full_name($cardUser)) ?>" loading="lazy">
    <?php else: ?>
        <div class="cms-user-card-avatar cms-user-card-avatar-empty" aria-hidden="true"><?= e($initials) ?></div>
    <?php endif; ?>

    <div class="cms-user-card-name"><?= e(full_name($cardUser)) ?></div>
    <?php if ($cardRole !== ''): ?>
        <div class="cms-user-card-sub"><?= e($cardRole) ?></div>
    <?php endif; ?>

    <?php if ($hasInfo): ?>
        <dl class="cms-user-card-info">
            <?php if ($cardPhone !== ''): ?>
                <dt>Телефон</dt>
                <dd><a href="tel:<?= e(preg_replace('/\s+/', '', $cardPhone)) ?>"><?= e($cardPhone) ?></a></dd>
            <?php endif; ?>
            <?php if ($cardEmail !== ''): ?>
                <dt>Email</dt>
                <dd><a href="mailto:<?= e($cardEmail) ?>"><?= e($cardEmail) ?></a></dd>
            <?php endif; ?>
            <?php if ($cardTelegram !== ''): ?>
                <dt>Telegram</dt>
                <dd><a href="<?= e($cardTelegramLink) ?>" target="_blank" rel="noopener">@<?= e($cardTelegram) ?></a></dd>
            <?php endif; ?>
            <?php if ($cardBirthday !== ''): ?>
                <dt>Дата рождения</dt>
                <dd>
                    <?= e($cardBirthday) ?>
                    <?php if ($cardAge !== null): ?>
                        <span class="text-body-secondary">· <?= (int)$cardAge ?> <?= age_word((int)$cardAge) ?></span>
                    <?php endif; ?>
                </dd>
            <?php endif; ?>
        </dl>
    <?php endif; ?>

    <?php if ($cardPdNote !== ''): ?>
        <p class="cms-user-card-note small text-body-secondary mb-0"><?= e($cardPdNote) ?></p>
    <?php endif; ?>
</div>
