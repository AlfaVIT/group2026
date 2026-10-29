<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Персональные данные</div>
    <div class="card-body">
        <?php if ($pdConsent): ?>
            <p class="text-body-secondary small mb-2">
                Согласие на обработку персональных данных предоставлено. Они используются только
                для коммуникации внутри группы и не передаются третьим лицам.
            </p>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('consent') ?>"
                   target="_blank" rel="noopener">Соглашение</a>
                <form method="post" action="<?= url('profile_save') ?>"
                      data-no-ajax
                      onsubmit="return confirm('Отозвать согласие? Телефон, email, дата рождения и Telegram будут удалены.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pd_revoke">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Отозвать согласие</button>
                </form>
            </div>
        <?php else: ?>
            <p class="text-body-secondary small mb-2">
                Для работы с персональными данными (телефон, email, дата рождения, Telegram) необходимо
                согласие. До его получения эти поля недоступны для редактирования. Данные используются
                только для коммуникации внутри группы и не передаются третьим лицам.
            </p>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('consent') ?>"
                   target="_blank" rel="noopener">Соглашение</a>
                <form method="post" action="<?= url('profile_save') ?>" data-no-ajax>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pd_consent">
                    <button class="btn btn-primary btn-sm" type="submit">Согласиться</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-12 col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Мои данные</div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="text-body-secondary">Роль:</span>
                    <span class="badge <?= role_badge_class($user['role']) ?>"><?= e($user['role']) ?></span>
                </div>
                <div class="mb-3">
                    <span class="text-body-secondary">Устройство:</span>
                    <span class="small" title="<?= e($deviceUa ?? '') ?>"><?= e($device ?? '') ?></span>
                </div>

                <hr class="my-3">
                <div class="fw-semibold small mb-2">Редактировать</div>
                <form method="post" action="<?= url('profile_save') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="data">
                    <div class="col-12">
                        <label class="form-label" for="p_login">Логин</label>
                        <input class="form-control" type="text" id="p_login" name="login" maxlength="64"
                               value="<?= e($user['login']) ?>" required>
                        <div class="form-text">По логину или email можно входить на сайт</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="p_name">Имя</label>
                        <input class="form-control" type="text" id="p_name" name="name" maxlength="100"
                               value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="p_email">Email</label>
                        <input class="form-control" type="email" id="p_email" name="email"
                               value="<?= e($user['email']) ?>" placeholder="необязательно"
                               <?= $pdConsent ? '' : 'disabled' ?>>
                        <div class="form-text">По email можно входить на сайт и восстанавливать пароль</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="p_telegram">Telegram</label>
                        <input class="form-control" type="text" id="p_telegram" name="telegram" maxlength="64"
                               value="<?= e($user['telegram']) ?>"
                               placeholder="Можно указать @username, ник или ссылку t.me/..."
                               <?= $pdConsent ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="p_phone">Телефон</label>
                        <div class="input-group">
                            <input class="form-control" type="tel" id="p_phone" name="phone" data-phone-mask maxlength="18"
                                   value="<?= e($user['phone']) ?>" placeholder="+7 (900) 000-00-00"
                                   <?= $pdConsent ? '' : 'disabled' ?>>
                            <div class="input-group-text bg-transparent">
                                <input class="form-check-input mt-0" type="checkbox" name="hide_phone" id="p_hide_phone" value="1"
                                    <?= !empty($user['hide_phone']) ? 'checked' : '' ?> <?= $pdConsent ? '' : 'disabled' ?>>
                            </div>
                            <label class="input-group-text bg-transparent" for="p_hide_phone">Скрывать</label>
                        </div>
                        <div class="form-text">Номер не будет показываться другим участникам</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">День рождения</label>
                        <?php
                        $bp = birthday_parts($user['birthday'] ?? '');
                        $bNoYear = !birthday_has_year($user['birthday'] ?? '');
                        ?>
                        <div class="input-group">
                            <input class="form-control" type="text" id="p_birthday_day" name="birthday_day" inputmode="numeric"
                                   maxlength="2" data-bday-mask="2" list="bday-day-list" placeholder="ДД"
                                   value="<?= e($bp['day']) ?>" <?= $pdConsent ? '' : 'disabled' ?>>
                            <input class="form-control" type="text" id="p_birthday_month" name="birthday_month" inputmode="numeric"
                                   maxlength="2" data-bday-mask="2" list="bday-month-list" placeholder="ММ"
                                   value="<?= e($bp['month']) ?>" <?= $pdConsent ? '' : 'disabled' ?>>
                            <input class="form-control <?= $bNoYear ? 'd-none' : '' ?>" type="text" id="p_birthday_year"
                                   name="birthday_year" inputmode="numeric" maxlength="4" data-bday-mask="4"
                                   list="bday-year-list" placeholder="ГГГГ" value="<?= e($bp['year']) ?>"
                                   <?= $pdConsent ? '' : 'disabled' ?>>
                            <div class="input-group-text">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="p_birthday_no_year"
                                           name="birthday_no_year" value="1" <?= $bNoYear ? 'checked' : '' ?>
                                           <?= $pdConsent ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="p_birthday_no_year">без года</label>
                                </div>
                            </div>
                        </div>
                        <datalist id="bday-day-list"><?php for ($i = 1; $i <= 31; $i++) { echo '<option value="' . $i . '">'; } ?></datalist>
                        <datalist id="bday-month-list"><?php for ($i = 1; $i <= 12; $i++) { echo '<option value="' . $i . '">'; } ?></datalist>
                        <datalist id="bday-year-list"><?php foreach (birthday_year_options() as $y) { echo '<option value="' . e($y) . '">'; } ?></datalist>
                        <div class="form-text">Год неизвестен — отметьте «без года», возраст выводиться не будет</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Сохранить данные</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Аватар</div>
            <div class="card-body" id="avatar-block">
                <div class="d-flex gap-3 align-items-start">
                    <div class="flex-shrink-0">
                        <?php if (avatar_url($user) !== ''): ?>
                            <img class="cms-avatar" style="width:96px;height:96px;" src="<?= e(avatar_url($user)) ?>" alt="Аватар">
                        <?php else: ?>
                            <div class="cms-avatar d-inline-flex align-items-center justify-content-center border bg-body-secondary text-body-secondary"
                                 style="width:96px;height:96px;">Нет</div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <form method="post" action="<?= url('profile_save') ?>" enctype="multipart/form-data"
                              data-ajax-update="#avatar-block" class="mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="avatar">
                            <div class="mb-2">
                                <input class="form-control form-control-sm" type="file" id="avatar" name="avatar"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">JPG, PNG, GIF или WebP, до 5 МБ.
                                    Будет уменьшено до 100px по большей стороне.</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-primary btn-sm" type="submit">Загрузить аватар</button>
                                <?php if (avatar_url($user) !== ''): ?>
                                    <button class="btn btn-outline-danger btn-sm" type="submit"
                                            form="avatar-remove-form">Удалить</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if (avatar_url($user) !== ''): ?>
                            <form method="post" action="<?= url('profile_save') ?>" id="avatar-remove-form"
                                  class="d-none" onsubmit="return confirm('Удалить аватар?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="avatar_remove">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Обращение</div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">
                    Как к вам обращаться на встречах: брат или сестра.
                </p>
                <form method="post" action="<?= url('profile_save') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="address_term">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="address_term" value="брат" <?= $user['address_term'] === 'брат' ? 'checked' : '' ?> id="at_b">
                        <label class="form-check-label" for="at_b">Брат</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="address_term" value="сестра" <?= $user['address_term'] === 'сестра' ? 'checked' : '' ?> id="at_s">
                        <label class="form-check-label" for="at_s">Сестра</label>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Сохранить обращение</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Тема оформления</div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">
                    Выберите тему для отображения сайта. «По умолчанию» — используется тема из настроек сайта.
                </p>
                <form method="post" action="<?= url('profile_save') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="theme">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <select class="form-select form-select-sm" name="theme" style="max-width: 260px;">
                            <option value="" <?= in_array($user['theme'] ?? '', ['', 'default'], true) ? 'selected' : '' ?>>
                                По умолчанию (на сайте: <?= e(THEME_LABELS[theme_scheme()]) ?>)
                            </option>
                            <?php foreach (THEMES as $t): if ($t === 'default') continue; ?>
                                <option value="<?= e($t) ?>" <?= ($user['theme'] ?? '') === $t ? 'selected' : '' ?>>
                                    <?= e(THEME_LABELS[$t]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Размер текста</div>
            <div class="card-body">
                <p class="text-body-secondary small mb-3">
                    Выберите удобный размер текста для всех страниц сайта.
                </p>
                <form method="post" action="<?= url('font_size_save') ?>" class="cms-font-size d-flex align-items-center gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="font_size">
                    <button type="submit" name="font_size" value="<?= user_font_size($user) - FONT_SIZE_STEP ?>"
                            class="btn btn-outline-secondary cms-a-btn"
                            title="Уменьшить размер текста"
                            <?= user_font_size($user) <= FONT_SIZE_MIN ? 'disabled' : '' ?>>А−</button>
                    <span class="badge text-bg-light border px-3 py-2"><?= (int)user_font_size($user) ?>%</span>
                    <button type="submit" name="font_size" value="<?= user_font_size($user) + FONT_SIZE_STEP ?>"
                            class="btn btn-outline-secondary cms-a-btn"
                            title="Увеличить размер текста"
                            <?= user_font_size($user) >= FONT_SIZE_MAX ? 'disabled' : '' ?>>А+</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Сменить пароль</div>
            <div class="card-body">
                <form method="post" action="<?= url('profile_save') ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="col-12">
                        <label class="form-label" for="current_password">Текущий пароль</label>
                        <input class="form-control" type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="new_password">Новый пароль</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="new_password_confirm">Повторите новый пароль</label>
                        <input class="form-control" type="password" id="new_password_confirm" name="new_password_confirm" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Изменить пароль</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($tgConfigured): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Бот Telegram</div>
            <div class="card-body">
                <?php if ($tgLinked): ?>
                    <p class="text-body-secondary small mb-0">
                        <span class="badge text-bg-success">Подключено</span>
                        <?php if ($tgUsername !== ''): ?>
                            <span class="ms-1">@<?= e($tgUsername) ?></span>
                        <?php endif; ?>
                    </p>
                <?php elseif ($tgLink !== ''): ?>
                    <p class="text-body-secondary small mb-2">
                        Нажмите кнопку — откроется чат с ботом. В чате нажмите «Запустить»,
                        и сообщения группы будут приходить сюда.
                    </p>
                    <a class="btn btn-primary" href="<?= e($tgLink) ?>" target="_blank" rel="noopener">
                        Подключить бота
                    </a>
                <?php else: ?>
                    <p class="text-body-secondary small mb-0">Не хватает настроек бота (токена или @username).</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>