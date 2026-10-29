<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Главная</h1>
        <p class="text-body-secondary mb-0">Календарь встреч, дни рождения и контакты участников</p>
    </div>
    <?php if (Auth::can('manage_meetings')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?= url('meeting_edit') ?>">Новая встреча</a>
            <a class="btn btn-outline-primary" href="<?= url('place_edit') ?>">Новое место</a>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">

<?php partial('calendar_strip', ['strip' => $strip + ['page' => 'home']]); ?>

<?php if ($selected): ?>
    <h2 class="h5 mb-3">Выбранная встреча</h2>
    <?php partial('meeting_card', [
        'meeting' => $selected,
        'roleAssignment' => $roleAssignment,
        'attendanceSummary' => $attendanceSummary,
        'myAttendance' => $myAttendance,
    ]); ?>
<?php else: ?>
    <div class="alert alert-info">
        <?= $upcoming || $past
            ? 'В этом месяце встреч нет. Нажмите на дату со встречей в календаре выше, чтобы увидеть сведения.'
            : 'Встреч пока нет.' . (Auth::can('manage_meetings') ? ' Создайте первую встречу кнопкой «Новая встреча».' : '') ?>
    </div>
<?php endif; ?>

    </div>

    <div class="col-lg-4">
        <?php $bdMonthsAll = [1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
            'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
        $cur = (int)date('n');
        $bdMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $n = ($cur + $i - 1) % 12 + 1;
            $bdMonths[$n] = $bdMonthsAll[$n];
        } ?>
        <?php $bdBadgeClass = ['today' => 'text-bg-success', 'passed' => 'text-bg-secondary', 'upcoming' => 'text-bg-warning'];
        $bdWord = ['today' => 'Сегодня исполняется', 'passed' => 'Исполнилось', 'upcoming' => 'Исполняется']; ?>

        <div class="card shadow-sm cms-bd-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Дни рождения</h2>
                </div>
                <ul class="nav cms-bd-tabs mb-3">
                    <?php foreach ($bdMonths as $n => $name): ?>
                        <li class="nav-item">
                            <button type="button" class="nav-link<?= $n === (int)date('n') ? ' active' : '' ?>"
                                    data-bd-month="<?= $n ?>"><?= $name ?><?= $bdYears[$n] !== (int)date('Y') ? ' ' . (int)$bdYears[$n] : '' ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php foreach ($bdMonths as $n => $name): ?>
                    <div class="bd-panel<?= $n === (int)date('n') ? '' : ' d-none' ?>" data-bd-panel="<?= $n ?>">
                        <?php if (empty($birthdays[$n])): ?>
                            <p class="text-body-secondary small mb-0">В <?= mb_strtolower($name) ?> дней рождения нет.</p>
                        <?php else: ?>
                            <?php foreach ($birthdays[$n] as $bd): ?>
                                <div class="cms-bd-row d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                                    <span class="d-flex flex-wrap align-items-center gap-2">
                                        <?php if ($bd['avatarUrl'] !== ''): ?>
                                            <img class="cms-avatar" style="width:26px;height:26px;" src="<?= e($bd['avatarUrl']) ?>" alt="">
                                        <?php endif; ?>
                                        <strong><?= e($bd['name']) ?></strong>
                                        <span class="text-body-secondary small"><?= (int)$bd['day'] ?>-е <?= e($bd['month']) ?><?= (int)$bd['year'] !== (int)date('Y') ? ' ' . (int)$bd['year'] : '' ?></span>
                                        <?php if ($bd['status'] === 'today'): ?>
                                            <span class="badge text-bg-success">Сегодня!</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="badge rounded-pill <?= $bdBadgeClass[$bd['status']] ?? 'text-bg-warning' ?>">
                                        <?php if ($bd['turns'] === null): ?>
                                            День рождения
                                        <?php else: ?>
                                            <?= $bdWord[$bd['status']] ?? 'Исполняется' ?> <?= (int)$bd['turns'] ?> <?= age_word((int)$bd['turns']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($contacts): ?>
        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Контакты участников</h2>
                </div>
                    <div class="btn-group btn-group-sm mb-3" id="contactFilter" role="group" aria-label="Фильтр по обращению">
                        <button type="button" class="btn btn-outline-secondary active" data-cf="">Все</button>
                        <button type="button" class="btn btn-outline-secondary" data-cf="брат">Братья</button>
                        <button type="button" class="btn btn-outline-secondary" data-cf="сестра">Сёстры</button>
                    </div>
                    <div class="cms-contacts">
                        <?php foreach ($contacts as $u): ?>
                            <?php $phone = $u['phone']; ?>
                            <div class="cms-contact-row py-2 border-bottom" data-term="<?= e($u['address_term']) ?>">
                                <div class="fw-semibold small d-flex align-items-center gap-2">
                                    <?php if (avatar_url($u) !== ''): ?>
                                        <img class="cms-avatar" style="width:26px;height:26px;" src="<?= e(avatar_url($u)) ?>" alt="">
                                    <?php endif; ?>
                                    <span><?= e(full_name($u)) ?></span>
                                </div>
                                <div class="small text-body-secondary">
                                    <?php if ($phone): ?>
                                        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
                                    <?php endif; ?>
                                    <?php if ($u['email']): ?>
                                        <?= $phone ? '<span class="mx-1 text-body-tertiary">·</span>' : '' ?>
                                        <a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a>
                                    <?php endif; ?>
                                    <?php if (telegram_nick($u['telegram']) !== ''): ?>
                                        <?= ($phone || $u['email']) ? '<span class="mx-1 text-body-tertiary">·</span>' : '' ?>
                                        <a href="<?= e(telegram_link($u['telegram'])) ?>" target="_blank" rel="noopener">@<?= e(telegram_nick($u['telegram'])) ?></a>
                                    <?php endif; ?>
                                    <?php if (!$phone && !$u['email'] && telegram_nick($u['telegram']) === ''): ?>
                                        <span class="text-body-tertiary">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>