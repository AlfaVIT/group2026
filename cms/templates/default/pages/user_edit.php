<?php
$isEdit = (bool)$user;
$isActiveUser = !empty($data['is_active']);
$loginRequired = $isActiveUser;
$bp = isset($data['birthday_day'])
    ? ['day' => (string)($data['birthday_day'] ?? ''), 'month' => (string)($data['birthday_month'] ?? ''), 'year' => (string)($data['birthday_year'] ?? '')]
    : birthday_parts($data['birthday'] ?? '');
$bNoYear = isset($data['birthday_no_year'])
    ? (int)$data['birthday_no_year'] === 1
    : !birthday_has_year($data['birthday'] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= e($title) ?></h1>
    <?php if ($isEdit): ?>
        <a class="btn btn-outline-secondary" href="<?= url('settings', ['tab' => 'users']) ?>">К списку пользователей</a>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('user_save') ?>" class="g-3" data-user-edit="1">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent">Обращение</label>
                <select class="form-select" name="address_term">
                    <?php foreach (ADDRESS_TERMS as $term): ?>
                        <option value="<?= e($term) ?>" <?= ($data['address_term'] ?? 'брат') === $term ? 'selected' : '' ?>><?= e($term) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control" type="text" id="name" name="name" placeholder="Имя / Обращение" required value="<?= e($data['name'] ?? '') ?>">
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="role">Роль</label>
                <select class="form-select" id="role" name="role">
                    <?php foreach (ROLES as $role): ?>
                        <option value="<?= e($role) ?>" <?= ($data['role'] ?? 'СестраБрат') === $role ? 'selected' : '' ?>>
                            <?= e($role) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group-text bg-transparent">
                    <input class="form-check-input mt-0" type="checkbox" name="is_active" id="is_active" value="1"
                        <?= ($data['is_active'] ?? 1) ? 'checked' : '' ?>>
                </div>
                <label class="input-group-text bg-transparent" for="is_active">Активен</label>
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="login">Логин</label>
                <input class="form-control" type="text" id="login" name="login" maxlength="64"
                    value="<?= e($data['login'] ?? '') ?>" placeholder="Необязателен для неактивных пользователей"
                    <?= $loginRequired ? 'required' : '' ?>>
            </div>
            <div class="form-text mb-3" style="margin-top:-0.75rem;">
                По логину или email участник входит на сайт. Для неактивных пользователей логин и пароль можно оставить
                пустыми: при активации учётной записи потребуется указать логин и пароль.
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="password">Пароль<?= $isEdit ? ' (оставьте пустым, чтобы не менять)' : '' ?></label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="password_confirm">Повторите пароль</label>
                <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
            </div>
        </div>

        <div class="col-md-6">
            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="phone">Телефон</label>
                <input class="form-control" type="tel" id="phone" name="phone" data-phone-mask maxlength="18"
                    value="<?= e($data['phone'] ?? '') ?>" placeholder="+7 (900) 000-00-00">
                <div class="input-group-text bg-transparent">
                    <input class="form-check-input mt-0" type="checkbox" name="hide_phone" id="hide_phone" value="1"
                        <?= !empty($data['hide_phone']) ? 'checked' : '' ?>>
                </div>
                <label class="input-group-text bg-transparent" for="hide_phone">Скрывать</label>
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="telegram">Telegram</label>
                <input class="form-control" type="text" id="telegram" name="telegram" maxlength="64"
                    value="<?= e($data['telegram'] ?? '') ?>" placeholder="Можно указать @username, ник или ссылку t.me/...">
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" maxlength="128"
                    value="<?= e($data['email'] ?? '') ?>" placeholder="необязательно">
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text bg-transparent">День рождения</label>
                <input class="form-control" type="text" id="ub_birthday_day" name="birthday_day" inputmode="numeric"
                       maxlength="2" data-bday-mask="2" list="bday-day-list" placeholder="ДД"
                       value="<?= e($bp['day']) ?>">
                <input class="form-control" type="text" id="ub_birthday_month" name="birthday_month" inputmode="numeric"
                       maxlength="2" data-bday-mask="2" list="bday-month-list" placeholder="ММ"
                       value="<?= e($bp['month']) ?>">
                <input class="form-control <?= $bNoYear ? 'd-none' : '' ?>" type="text" id="ub_birthday_year"
                       name="birthday_year" inputmode="numeric" maxlength="4" data-bday-mask="4"
                       list="bday-year-list" placeholder="ГГГГ" value="<?= e($bp['year']) ?>">
                <div class="input-group-text">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="ub_birthday_no_year"
                               name="birthday_no_year" value="1" <?= $bNoYear ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ub_birthday_no_year">без года</label>
                    </div>
                </div>
            </div>
            <datalist id="bday-day-list"><?php for ($i = 1; $i <= 31; $i++) { echo '<option value="' . $i . '">'; } ?></datalist>
            <datalist id="bday-month-list"><?php for ($i = 1; $i <= 12; $i++) { echo '<option value="' . $i . '">'; } ?></datalist>
            <datalist id="bday-year-list"><?php foreach (birthday_year_options() as $y) { echo '<option value="' . e($y) . '">'; } ?></datalist>
        </div>

    </div>


    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-outline-secondary" href="<?= url('settings', ['tab' => 'users']) ?>">Отмена</a>
    </div>
</form>