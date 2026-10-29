<?php
$webhookUrl = $configured ? url('telegram_webhook') : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Telegram-бот</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('home') ?>">← На главную</a>
</div>

<?php if (!$configured): ?>
    <div class="alert alert-warning">
        Бот ещё не настроен: заполните токен ниже. Как создать бота: напишите
        <b>@BotFather</b>, команда <code>/newbot</code>, затем скопируйте выданный токен.
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Настройки бота</div>
    <div class="card-body">
        <form method="post" action="<?= url('telegram_save') ?>" class="row g-3" style="max-width: 640px;">
            <?= csrf_field() ?>
            <div class="col-md-7">
                <label class="form-label" for="tg_token">Токен бота</label>
                <input class="form-control" type="text" id="tg_token" name="telegram_bot_token"
                       value="<?= e($token) ?>" placeholder="123456789:AA…" autocomplete="off">
                <div class="form-text">Хранится в настройках сайта и виден только администраторам</div>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="tg_username">@username бота</label>
                <input class="form-control" type="text" id="tg_username" name="telegram_bot_username"
                       value="<?= e($botUsername) ?>" placeholder="group2026_bot">
                <div class="form-text">Нужен для ссылки подключения участников</div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Сохранить настройки</button>
            </div>
        </form>
    </div>
</div>

<?php if ($configured): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Webhook</div>
        <div class="card-body">
            <p class="text-body-secondary small mb-2">
                Webhook принимает команды участников (например, подключение по ссылке).
                Установите его один раз после сохранения токена.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= url('telegram_webhook') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set">
                    <button class="btn btn-primary btn-sm" type="submit">Установить webhook</button>
                </form>
                <form method="post" action="<?= url('telegram_webhook') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Отключить webhook</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Подключение участников</div>
        <div class="card-body">
            <p class="text-body-secondary small">
                Участники подключаются сами: в своём профиле они нажимают «Подключить бота».
                Здесь видно, кто уже подключился к боту.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Роль</th>
                        <th>Telegram</th>
                        <th>Подключён к боту</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e(full_name($u)) ?></td>
                            <td><span class="badge <?= role_badge_class($u['role']) ?>"><?= e($u['role']) ?></span></td>
                            <td class="small">
                                <?php if (telegram_nick($u['telegram']) !== ''): ?>
                                    <a href="<?= e(telegram_link($u['telegram'])) ?>" target="_blank" rel="noopener">@<?= e(telegram_nick($u['telegram'])) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if (!empty($u['telegram_chat_id'])): ?>
                                    <span class="badge text-bg-success">подключён</span>
                                    <?php if (!empty($u['telegram_username'])): ?>
                                        <span class="text-body-secondary">@<?= e($u['telegram_username']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">нет</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Рассылка</div>
        <div class="card-body">
            <form method="post" action="<?= url('telegram_send') ?>" class="row g-3"
                  onsubmit="return confirm('Отправить сообщение выбранным участникам?');">
                <?= csrf_field() ?>
                <div class="col-12">
                    <label class="form-label" for="tg_text">Текст сообщения</label>
                    <textarea class="form-control" id="tg_text" name="text" rows="5" required></textarea>
                    <div class="form-text">Длинные сообщения разбиваются на части автоматически</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tg_parse">Форматирование</label>
                    <select class="form-select" id="tg_parse" name="parse_mode">
                        <option value="">Без форматирования</option>
                        <option value="HTML">HTML (&lt;b&gt;, &lt;i&gt;, &lt;a href="…"&gt;)</option>
                        <option value="Markdown">Markdown (*жирный*, _курсив_)</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-label">Получатели</div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" value="all"
                               id="scope_all" checked>
                        <label class="form-check-label" for="scope_all">
                            Все участники, подключённые к боту
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope" value="present"
                               id="scope_present">
                        <label class="form-check-label" for="scope_present">Очные участники встречи:</label>
                        <select class="form-select form-select-sm mt-1" id="scope_meeting" name="meeting_id"
                                style="max-width: 420px;">
                            <option value="">— выберите встречу —</option>
                            <?php foreach ($meetings as $m): ?>
                                <option value="<?= (int)$m['id'] ?>"><?= rdate($m['date']) ?><?= $m['topic'] !== '' ? ' — ' . e($m['topic']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Отправить сообщение</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>