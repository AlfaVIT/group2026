<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Мои данные</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-body-secondary">Имя</dt>
                    <dd class="col-sm-8"><?= e(full_name($user)) ?></dd>
                    <dt class="col-sm-4 text-body-secondary">Роль</dt>
                    <dd class="col-sm-8"><span class="badge <?= role_badge_class($user['role']) ?>"><?= e($user['role']) ?></span></dd>
                    <dt class="col-sm-4 text-body-secondary">Email</dt>
                    <dd class="col-sm-8"><?= e($user['email']) ?></dd>
                    <dt class="col-sm-4 text-body-secondary">Телефон</dt>
                    <dd class="col-sm-8"><?= e($user['phone']) ?: '—' ?></dd>
                    <dt class="col-sm-4 text-body-secondary">День рождения</dt>
                    <dd class="col-sm-8"><?= $user['birthday'] ? e(date('d.m.Y', strtotime($user['birthday']))) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Сменить пароль</div>
            <div class="card-body">
                <form method="post" action="<?= url('profile_save') ?>" class="row g-3" style="max-width: 420px;">
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
    </div>
</div>