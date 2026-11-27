<?php
$canManage = $canManage ?? Auth::can('manage_settings');
$tabs = [];
if ($canManage) {
    $tabs['general'] = 'Общее';
    $tabs['users'] = 'Пользователи';
}
$tabs['places'] = 'Места встреч';
if ($canManage) {
    $tabs['meetings'] = 'Встречи';
    $tabs['maintenance'] = 'Обслуживание';
}
if ($canViewStats ?? false) {
    $tabs['stats'] = 'Статистика';
}
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
    <form method="post" action="<?= url('settings_save') ?>" class="row g-3" data-no-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="general">

        <div class="col-md-4">
            <label class="form-label" for="site_name">Название сайта</label>
            <input class="form-control" type="text" id="site_name" name="site_name" required
                   value="<?= e($siteName) ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label" for="theme">Тема оформления</label>
            <select class="form-select" id="theme" name="theme">
                <?php foreach ($themes as $themeName): ?>
                    <option value="<?= e($themeName) ?>" <?= $theme === $themeName ? 'selected' : '' ?>>
                        <?= e(THEME_LABELS[$themeName] ?? $themeName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Пользователи могут выбрать свою тему в профиле</div>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="mail_from">Email отправителя писем</label>
            <input class="form-control" type="email" id="mail_from" name="mail_from"
                   value="<?= e($mailFrom) ?>">
            <div class="form-text">Используется в письмах восстановления пароля</div>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="admin_email">Почта администратора</label>
            <input class="form-control" type="email" id="admin_email" name="admin_email"
                   value="<?= e($adminEmail ?? '') ?>" placeholder="admin@example.ru">
            <div class="form-text">Системные уведомления и резервные копии</div>
        </div>

        <div class="col-md-8">
            <label class="form-label" for="stream_url">Ссылка на трансляцию</label>
            <input class="form-control" type="url" id="stream_url" name="stream_url"
                   value="<?= e($streamUrl ?? '') ?>" placeholder="https://">
            <div class="form-text">
                Общая ссылка на трансляцию. Предлагается во встречах, где не указана своя ссылка.
            </div>
        </div>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="finance_enabled"
                       name="finance_enabled" value="1"
                       <?= ($financeEnabled ?? true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="finance_enabled">Финансы</label>
            </div>
            <div class="form-text">
                Раздел «Казна» и блок «Расходы по встрече» на странице встречи.
                Если выключить, эти разделы скрыты для всех участников.
            </div>
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
                <th>Telegram</th>
                <th>День рождения</th>
                <th>Активен</th>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php $userAvatar = user_avatar_url($u); ?>
                            <?php if ($userAvatar !== ''): ?>
                                <img class="cms-avatar" style="width:28px;height:28px;" src="<?= e($userAvatar) ?>" alt="">
                            <?php endif; ?>
                            <span><?= e(full_name($u)) ?></span>
                        </div>
                        <div class="small text-body-secondary"><?= e($u['email'] !== '' ? $u['email'] : $u['login']) ?></div>
                    </td>
                    <td><span class="badge <?= role_badge_class($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td class="small"><?= (!empty($u['phone']) && empty($u['hide_phone'])) ? e($u['phone']) : '—' ?></td>
                    <td class="small">
                        <?php if (telegram_nick($u['telegram']) !== ''): ?>
                            <a href="<?= e(telegram_link($u['telegram'])) ?>" target="_blank" rel="noopener">@<?= e(telegram_nick($u['telegram'])) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        <?php if (!empty($u['telegram_chat_id'])): ?>
                            <span class="badge text-bg-success ms-1">бот ✓</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= $u['birthday'] ? e(birthday_display($u['birthday'])) : '—' ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge text-bg-success">Да</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Нет</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('user_edit', ['id' => $u['id']]) ?>"
                           title="Изменить" aria-label="Изменить"><?= ui_icon('edit') ?></a>
                        <?php if ((int)$u['id'] !== Auth::id()): ?>
                            <form method="post" action="<?= url('user_delete') ?>" class="d-inline"
                                  onsubmit="return confirm('Удалить пользователя?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        title="Удалить" aria-label="Удалить"><?= ui_icon('trash') ?></button>
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
                <th>Статус</th>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($places as $p): ?>
                <tr>
                    <td class="fw-semibold">
                        <div class="d-flex align-items-center gap-2">
                            <?php $placeAvatar = place_avatar_url($p); ?>
                            <?php if ($placeAvatar !== ''): ?>
                                <img class="cms-place-avatar" src="<?= e($placeAvatar) ?>" alt=""
                                     loading="lazy">
                            <?php else: ?>
                                <span class="cms-place-avatar cms-place-avatar-empty" aria-hidden="true"><?= ui_icon('home', 18) ?></span>
                            <?php endif; ?>
                            <span><?= e($p['title']) ?></span>
                        </div>
                    </td>
                    <td class="small"><?= $p['owner_name'] ? e(full_name(['address_term' => $p['owner_term'], 'name' => $p['owner_name']])) : '—' ?></td>
                    <td class="small"><?= e((string)$p['locality']) . ($p['street'] ? ', ' . e($p['street']) . ' ' . e($p['house'] . $p['house_letter']) : '') ?></td>
                    <td class="small"><?= (int)$p['photos_count'] ?></td>
                    <td class="small">
                        <?php if (!empty($p['is_active'])): ?>
                            <span class="badge text-bg-success">Активно</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Неактивно</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('place_edit', ['id' => $p['id']]) ?>"
                           title="Изменить" aria-label="Изменить"><?= ui_icon('edit') ?></a>
                        <form method="post" action="<?= url('place_delete') ?>" class="d-inline"
                              onsubmit="return confirm('Удалить место встречи?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                    title="Удалить" aria-label="Удалить"><?= ui_icon('trash') ?></button>
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
                <th>Метка</th>
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
                    <td><?= $m['tag'] !== '' ? '<span class="tag-badge">' . e($m['tag']) . '</span>' : '—' ?></td>
                    <td class="small"><?= e($m['place_title']) ?: '—' ?></td>
                    <td class="small"><?= (int)($meetingParticipants[$m['id']] ?? 0) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting', ['id' => $m['id']]) ?>"
                           title="Открыть" aria-label="Открыть"><?= ui_icon('eye') ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('meeting_edit', ['id' => $m['id']]) ?>"
                           title="Изменить" aria-label="Изменить"><?= ui_icon('edit') ?></a>
                        <form method="post" action="<?= url('meeting_delete') ?>" class="d-inline"
                              onsubmit="return confirm('Удалить встречу?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                    title="Удалить" aria-label="Удалить"><?= ui_icon('trash') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($tab === 'maintenance'): ?>
    <?php
    $lastSent = (string)($backup['last'] ?? '') !== ''
        ? date('d.m.Y H:i', strtotime((string)$backup['last']))
        : 'ещё не отправлялся';
    $lastError = (string)($backup['last_error'] ?? '');
    $noEmail = Setting::adminEmail() === '';
    ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= url('settings_save') ?>" class="card shadow-sm h-100">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="maintenance">
                <input type="hidden" name="action" value="save">
                <div class="card-header fw-semibold">Резервные копии базы</div>
                <div class="card-body">
                    <?php $backupOn = !empty($backup['enabled']); ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="backup_enabled"
                               name="backup_enabled" value="1"
                               <?= $backupOn ? 'checked' : '' ?>>
                        <label class="form-check-label" for="backup_enabled">Отправлять бекап базы на почту</label>
                        <div class="form-text">
                            Когда выключено, письма не уходят, даже если внешний планировщик вызывает URL.
                        </div>
                    </div>

                    <p class="mb-2">
                        Расписание задаётся не на сайте: бекап создаётся и отправляется по каждому
                        вызову внешнего планировщика (cron хостинга или внешний сервис) — командой
                        или URL из блока справа.
                    </p>
                    <div class="form-text mb-3">
                        Бекап уходит на «Почту администратора» (вкладка «Общее»).
                        <?php if ($noEmail): ?>
                            <span class="text-danger">Сейчас адрес не задан — отправка не сработает.</span>
                        <?php endif; ?>
                    </div>

                    <ul class="list-unstyled small text-body-secondary mb-0">
                        <li>Последняя отправка: <?= e($lastSent) ?></li>
                        <?php if ($lastError !== ''): ?>
                            <li class="text-danger">Последняя ошибка: <?= e($lastError) ?></li>
                        <?php endif; ?>
                    </ul>

                    <hr>
                    <h2 class="h6">Системные уведомления</h2>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="notify_bruteforce"
                               name="notify_bruteforce" value="1"
                               <?= !empty($notifyBruteforce) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_bruteforce">Попытка подбора пароля</label>
                        <div class="form-text">
                            Письмо, если за 10 минут было 5 и больше неудачных входов (не чаще раза в час).
                        </div>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="notify_admin_login"
                               name="notify_admin_login" value="1"
                               <?= !empty($notifyAdminLogin) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_admin_login">Попытка авторизации admin</label>
                        <div class="form-text">
                            Любая попытка входа с логином admin — удачная или нет (не чаще раза в 30 минут).
                        </div>
                    </div>

                    <hr>
                    <h2 class="h6">Почта (SMTP)</h2>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="smtp_enabled"
                               name="smtp_enabled" value="1"
                               <?= !empty($smtp['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smtp_enabled">
                            Отправлять письма через SMTP-сервер
                        </label>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-8">
                            <label class="form-label" for="smtp_host">Сервер</label>
                            <input class="form-control" type="text" id="smtp_host" name="smtp_host"
                                   value="<?= e($smtp['host'] ?? '') ?>" placeholder="smtp.mail.ru">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="smtp_port">Порт</label>
                            <input class="form-control" type="number" id="smtp_port" name="smtp_port"
                                   min="1" max="65535" value="<?= e($smtp['port'] ?? '465') ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="smtp_secure">Шифрование</label>
                            <select class="form-select" id="smtp_secure" name="smtp_secure">
                                <option value="ssl" <?= ($smtp['secure'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                                <option value="tls" <?= ($smtp['secure'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                                <option value="" <?= ($smtp['secure'] ?? '') === '' ? 'selected' : '' ?>>Без шифрования</option>
                            </select>
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label" for="smtp_user">Логин (адрес почты)</label>
                            <input class="form-control" type="email" id="smtp_user" name="smtp_user"
                                   value="<?= e($smtp['user'] ?? '') ?>" placeholder="noreply@opensite.su">
                        </div>
                        <div class="col-sm-8">
                            <label class="form-label" for="smtp_pass">Пароль</label>
                            <input class="form-control" type="password" id="smtp_pass" name="smtp_pass"
                                   autocomplete="new-password"
                                   placeholder="<?= !empty($smtp['has_pass']) ? 'сохранён — оставьте пустым, чтобы не менять' : 'пароль почтового ящика' ?>">
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        При включённом SMTP адрес отправителя совпадает с логином — иначе провайдер
                        отклонит письмо или испортит SPF. Для Mail.ru: smtp.mail.ru, 465, SSL,
                        пароль приложения (не основной пароль аккаунта).
                    </div>
                    <?php if (($mailLastError ?? '') !== ''): ?>
                        <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                            Последняя ошибка почты: <?= e($mailLastError) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" type="submit">Сохранить</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Внешний планировщик</div>
                <div class="card-body">
                    <p class="small text-body-secondary">
                        Каждый запуск команды или URL создаёт и отправляет бекап.
                        Периодичность задаётся снаружи — в crontab хостинга или во внешнем сервисе.
                    </p>
                    <label class="form-label small" for="cronCommand">Команда для cron</label>
                    <input class="form-control form-control-sm font-monospace" id="cronCommand" readonly
                           value="<?= e($cronCommand) ?>">
                    <label class="form-label small mt-2" for="cronUrl">Или URL для внешнего планировщика</label>
                    <input class="form-control form-control-sm font-monospace" id="cronUrl" readonly
                           value="<?= e($cronUrl) ?>">
                    <div class="form-text">В URL секретный токен — не пересылайте его.</div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Действия</div>
                <div class="card-body d-flex flex-column gap-2">
                    <form method="post" action="<?= url('settings_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="maintenance">
                        <input type="hidden" name="action" value="backup_now">
                        <button class="btn btn-outline-primary w-100" type="submit"
                                <?= $backupOn ? '' : 'disabled' ?>
                                <?= $backupOn ? '' : 'title="Отправка выключена в настройках"' ?>
                                onclick="return confirm('Отправить бекап базы сейчас?');">
                            Отправить бекап сейчас
                        </button>
                    </form>
                    <?php if (!$backupOn): ?>
                        <div class="form-text">Отправка выключена — включите переключатель выше.</div>
                    <?php endif; ?>
                    <form method="post" action="<?= url('settings_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="maintenance">
                        <input type="hidden" name="action" value="rotate_token">
                        <button class="btn btn-outline-secondary w-100" type="submit"
                                onclick="return confirm('Старый URL перестанет работать. Обновить токен?');">
                            Обновить токен
                        </button>
                    </form>
                    <form method="post" action="<?= url('settings_save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="maintenance">
                        <input type="hidden" name="action" value="mail_test">
                        <button class="btn btn-outline-success w-100" type="submit">
                            Отправить тестовое письмо
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($crypto)): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold">Шифрование персональных данных</div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="small">
                        <div>
                            Шифр:
                            <?php if ($crypto['available']): ?>
                                <span class="badge text-bg-success">AES-256-GCM</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">openssl недоступен</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            Ключ:
                            <?php if ($crypto['key_valid']): ?>
                                <span class="badge text-bg-success">на месте</span>
                            <?php elseif ($crypto['key_exists']): ?>
                                <span class="badge text-bg-danger">повреждён</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">нет файла</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            Режим:
                            <?php if ($crypto['enabled']): ?>
                                <span class="badge text-bg-success">включён</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">выключен</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            Открытых значений:
                            <span class="badge text-bg-<?= $crypto['open'] > 0 ? 'warning' : 'success' ?>">
                                <?= (int)$crypto['open'] ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!$crypto['enabled'] && $crypto['key_valid']): ?>
                        <div class="alert alert-secondary small mb-0">
                            Шифрование выключено: новые данные пишутся открыто. Чтобы включить,
                            создайте пустой файл <code>data/keys/enabled</code> рядом с ключом
                            и нажмите «Зашифровать оставшиеся данные».
                        </div>
                    <?php endif; ?>
                    <?php if (!$crypto['key_exists'] && $crypto['has_encrypted']): ?>
                        <div class="alert alert-danger small mb-0">
                            В базе есть зашифрованные данные, но файла ключа нет. Восстановите
                            <code>data/keys/master.key</code> из сохранённой копии — без него
                            персональные данные прочитать нельзя.
                        </div>
                    <?php elseif (!$crypto['key_exists']): ?>
                        <div class="alert alert-danger small mb-0">
                            Файла ключа нет. Пока он не создан, шифрование не включится.
                            Ключ создаётся при обновлении базы — обновите страницу после миграции.
                        </div>
                    <?php elseif ($crypto['key_valid']): ?>
                        <div class="alert alert-warning small mb-0">
                            Сохраните копию файла <code>data/keys/master.key</code> отдельно
                            от базы. Без ключа данные восстановить невозможно.
                        </div>
                    <?php endif; ?>
                    <?php if ($crypto['enabled'] && $crypto['open'] > 0): ?>
                        <form method="post" action="<?= url('settings_save') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="section" value="maintenance">
                            <input type="hidden" name="action" value="crypto_sweep">
                            <button class="btn btn-outline-warning w-100" type="submit"
                                    onclick="return confirm('Зашифровать оставшиеся персональные данные? Отменить это без копии базы нельзя.');">
                                Зашифровать оставшиеся данные
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($tab === 'stats'): ?>
    <?php
    $sortLink = static function (string $key) use ($sort, $dir): string {
        $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        return url('settings', ['tab' => 'stats', 'sort' => $key, 'dir' => $nextDir]);
    };
    $sortArrow = static function (string $key) use ($sort, $dir): string {
        if ($sort !== $key) {
            return '';
        }
        return $dir === 'asc' ? ' ▲' : ' ▼';
    };
    $colspan = count($months) + 3;
    ?>
    <div class="mb-3">
        <h2 class="h5 mb-1">Входы по месяцам</h2>
        <p class="text-body-secondary small mb-0">
            Участники, которые заходили на сайт: число входов по месяцам и дата последнего входа
        </p>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>
                    <a class="text-reset text-decoration-none"
                       href="<?= e($sortLink('name')) ?>">Участник<?= $sortArrow('name') ?></a>
                </th>
                <?php foreach ($months as $month): ?>
                    <th class="text-end text-nowrap small"><?= e($monthLabels[$month] ?? $month) ?></th>
                <?php endforeach; ?>
                <th class="text-end">Всего</th>
                <th class="text-end text-nowrap">
                    <a class="text-reset text-decoration-none"
                       href="<?= e($sortLink('last')) ?>">Последний вход<?= $sortArrow('last') ?></a>
                </th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$statsUsers): ?>
                <tr>
                    <td colspan="<?= $colspan ?>" class="text-body-secondary small">Входов пока не было</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($statsUsers as $u): ?>
                <?php
                $uid = (int)$u['id'];
                $data = $statsByUser[$uid] ?? ['months' => [], 'total' => 0, 'last' => ''];
                ?>
                <tr>
                    <td class="small">
                        <?= e(full_name($u)) ?>
                        <?php if ((int)$u['is_active'] !== 1): ?>
                            <span class="badge text-bg-secondary">отключён</span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($months as $month): ?>
                        <?php $count = (int)($data['months'][$month] ?? 0); ?>
                        <td class="text-end small<?= $count === 0 ? ' text-body-tertiary' : '' ?>">
                            <?= $count > 0 ? $count : '—' ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end fw-semibold"><?= (int)$data['total'] > 0 ? (int)$data['total'] : '—' ?></td>
                    <td class="small text-nowrap text-end">
                        <?= $data['last'] !== '' ? e(date('d.m.Y H:i', strtotime($data['last']))) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-text mt-2 mb-4">
        Столбцы — месяцы, в ячейках — число входов. Прочерк означает, что входов в этом месяце не было.
    </div>

    <div class="card shadow-sm">
        <div class="card-header p-0">
            <button class="btn btn-link w-100 text-start text-decoration-none py-2 px-3 fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2"
                    type="button" data-bs-toggle="collapse" data-bs-target="#visitsLog"
                    aria-expanded="false" aria-controls="visitsLog">
                <span>Входы</span>
                <span class="small text-body-secondary">последние <?= count($statsVisits) ?> записей</span>
            </button>
        </div>
        <div class="collapse" id="visitsLog">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Когда</th>
                            <th>Участник</th>
                            <th>IP</th>
                            <th>Устройство</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$statsVisits): ?>
                            <tr><td colspan="4" class="text-body-secondary small">Записей пока нет</td></tr>
                        <?php endif; ?>
                        <?php foreach ($statsVisits as $v): ?>
                            <tr>
                                <td class="small text-nowrap"><?= e(date('d.m.Y H:i', strtotime($v['created_at']))) ?></td>
                                <td class="small"><?= e(full_name($v)) ?></td>
                                <td class="small"><?= e($v['ip'] !== '' ? $v['ip'] : '—') ?></td>
                                <td class="small" title="<?= e($v['user_agent']) ?>"><?= e(visitor_device($v['user_agent'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>