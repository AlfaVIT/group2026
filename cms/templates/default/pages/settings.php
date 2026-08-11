<?php
$tabs = [
    'general' => 'Общее',
    'users' => 'Пользователи',
    'places' => 'Места встреч',
    'meetings' => 'Встречи',
];
?>
<div class="mb-3">
    <h1 class="h3 mb-0">Настройки</h1>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
               href="<?= url('settings', ['tab' => $key]) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'general'): ?>
    <form method="post" action="<?= url('settings_save') ?>" class="row g-3" style="max-width: 640px;">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="general">

        <div class="col-md-6">
            <label class="form-label" for="site_name">Название сайта</label>
            <input class="form-control" type="text" id="site_name" name="site_name" required
                   value="<?= e($siteName) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="theme">Шаблон (тема)</label>
            <select class="form-select" id="theme" name="theme">
                <?php foreach ($themes as $themeName): ?>
                    <option value="<?= e($themeName) ?>" <?= $theme === $themeName ? 'selected' : '' ?>>
                        <?= e($themeName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Новые темы размещаются в каталоге templates/</div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Сохранить настройки</button>
        </div>
    </form>

<?php elseif ($tab === 'users'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-body-secondary small">Учётные записи участников. Роли: Старший, Помощник, Казначей, СестраБрат, Гость.</div>
        <a class="btn btn-primary btn-sm" href="<?= url('user_edit') ?>">Добавить пользователя</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Участник</th>
                <th>Роль</th>
                <th>Телефон</th>
                <th>День рождения</th>
                <th>Активен</th>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div><?= e(full_name($u)) ?></div>
                        <div class="small text-body-secondary"><?= e($u['email']) ?></div>
                    </td>
                    <td><span class="badge <?= role_badge_class($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td class="small"><?= e($u['phone']) ?: '—' ?></td>
                    <td class="small"><?= $u['birthday'] ? e(date('d.m.Y', strtotime($u['birthday']))) : '—' ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge text-bg-success">Да</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('user_edit', ['id' => $u['id']]) ?>">Изменить</a>
                        <?php if ((int)$u['id'] !== Auth::id()): ?>
                            <form method="post" action="<?= url('user_delete') ?>" class="d-inline"
                                  onsubmit="return confirm('Удалить пользователя?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'places'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-body-secondary small">Места проведения встреч</div>
        <a class="btn btn-primary btn-sm" href="<?= url('place_edit') ?>">Добавить место</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Наименование</th>
                <th>Хозяин</th>
                <th>Адрес</th>
                <th>Фото</th>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($places as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['title']) ?></td>
                    <td class="small"><?= $p['owner_name'] ? e(full_name(['address_term' => $p['owner_term'], 'name' => $p['owner_name']])) : '—' ?></td>
                    <td class="small"><?= $p['locality'] . ($p['street'] ? ', ' . e($p['street']) . ' ' . e($p['house'] . $p['house_letter']) : '') ?></td>
                    <td class="small"><?= (int)$p['photos_count'] ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('place_edit', ['id' => $p['id']]) ?>">Изменить</a>
                        <form method="post" action="<?= url('place_delete') ?>" class="d-inline"
                              onsubmit="return confirm('Удалить место встречи?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'meetings'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-body-secondary small">Все встречи</div>
        <a class="btn btn-primary btn-sm" href="<?= url('meeting_edit') ?>">Добавить встречу</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Дата</th>
                <th>Тема</th>
                <th>Место</th>
                <th>Участников</th>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($meetings as $m): ?>
                <tr>
                    <td class="small"><?= rdate($m['date']) ?></td>
                    <td><?= e($m['topic']) ?: '—' ?></td>
                    <td class="small"><?= e($m['place_title']) ?: '—' ?></td>
                    <td class="small"><?= (int)$m['participants_count'] ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting', ['id' => $m['id']]) ?>">Открыть</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting_edit', ['id' => $m['id']]) ?>">Изменить</a>
                        <form method="post" action="<?= url('meeting_delete') ?>" class="d-inline"
                              onsubmit="return confirm('Удалить встречу?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>