<?php
$isEdit = (bool)$user;
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

<form method="post" action="<?= url('user_save') ?>" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
    <?php endif; ?>

    <div class="col-md-6">
        <label class="form-label">Обращение</label>
        <div>
            <?php foreach (ADDRESS_TERMS as $term): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="address_term" value="<?= e($term) ?>"
                           id="at_<?= e($term) ?>" <?= ($data['address_term'] ?? 'брат') === $term ? 'checked' : '' ?>>
                    <label class="form-check-label" for="at_<?= e($term) ?>"><?= e($term) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="name">Имя</label>
        <input class="form-control" type="text" id="name" name="name" required value="<?= e($data['name'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="phone">Телефон</label>
        <input class="form-control" type="text" id="phone" name="phone" value="<?= e($data['phone'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="birthday">День рождения</label>
        <input class="form-control" type="date" id="birthday" name="birthday" value="<?= e($data['birthday'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label" for="email">Email (логин)</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e($data['email'] ?? '') ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label" for="role">Роль</label>
        <select class="form-select" id="role" name="role">
            <?php foreach (ROLES as $role): ?>
                <option value="<?= e($role) ?>" <?= ($data['role'] ?? 'СестраБрат') === $role ? 'selected' : '' ?>>
                    <?= e($role) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   <?= ($data['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Активен</label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="password">Пароль<?= $isEdit ? ' (оставьте пустым, чтобы не менять)' : '' ?></label>
        <input class="form-control" type="password" id="password" name="password" autocomplete="new-password"
               <?= $isEdit ? '' : 'required' ?>>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="password_confirm">Повторите пароль</label>
        <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
               <?= $isEdit ? '' : 'required' ?>>
    </div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Сохранить</button>
        <a class="btn btn-outline-secondary" href="<?= url('settings', ['tab' => 'users']) ?>">Отмена</a>
    </div>
</form>