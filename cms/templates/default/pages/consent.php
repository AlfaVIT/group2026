<?php
$consented = false;
if (Auth::check()) {
    $uu = Auth::user();
    $consented = (int)($uu['personal_consent'] ?? 0) === 1;
}
$siteName = Setting::siteName();
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?= e(theme_scheme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Согласие на обработку персональных данных — <?= e($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url() ?>/fish.php">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= base_url() ?>/assets/css/style.css">
</head>
<body class="auth-body">
<main class="container" style="max-width: 720px; padding-top: 6vh;">
    <div class="card auth-card">
        <div class="card-body p-4">
            <div class="auth-brand">
                <?php partial('fish', ['width' => 34]); ?>
                <h1 class="title">Согласие на обработку персональных данных</h1>
                <div class="rule"></div>
                <p class="subtitle text-body-secondary small">в соответствии с Федеральным законом № 152-ФЗ</p>
            </div>

            <p class="small">
                Настоящее согласие даётся пользователем сайта «<?= e($siteName) ?>» (далее — «Сайт»)
                в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных».
            </p>

            <h2 class="h5 mt-4">Какие данные обрабатываются</h2>
            <ul>
                <li>номер телефона;</li>
                <li>адрес электронной почты;</li>
                <li>дата рождения;</li>
                <li>ник в Telegram.</li>
            </ul>

            <h2 class="h5 mt-4">Цель обработки</h2>
            <p class="small mb-0">
                Указанные данные используются исключительно для коммуникации внутри группы катехизации
                (взаимодействие с участниками, организация встреч, оповещения и обратная связь).
            </p>

            <h2 class="h5 mt-4">Передача третьим лицам</h2>
            <p class="small mb-0">
                Персональные данные <b>не передаются третьим лицам</b> и не используются для рассылок
                или целей, не связанных с деятельностью группы.
            </p>

            <h2 class="h5 mt-4">Согласие и его отзыв</h2>
            <p class="small">
                Согласие можно дать при входе на сайт или на странице «Мой профиль». Отозвать согласие
                можно в любой момент на странице «Мой профиль» — после отзыва указанные выше данные
                будут удалены, а их поля станут недоступны для редактирования до нового согласия.
            </p>

            <p class="small text-body-secondary mb-0">
                Продолжая пользоваться Сайтом и отметив соответствующее согласие, вы подтверждаете
                ознакомление с настоящим соглашением.
            </p>

            <div class="mt-4 d-flex gap-2">
                <?php if (Auth::check()): ?>
                    <a class="btn btn-primary" href="<?= url('profile') ?>">Вернуться в профиль</a>
                    <?php if (!$consented): ?>
                        <form method="post" action="<?= url('profile_save') ?>" data-no-ajax>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="pd_consent">
                            <button class="btn btn-outline-primary" type="submit">Согласиться</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= url('login') ?>">Вернуться ко входу</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script src="<?= base_url() ?>/assets/vendor/jquery/jquery.min.js"></script>
<script src="<?= base_url() ?>/assets/js/app.js"></script>
<?php partial('cookie_banner'); ?>
</body>
</html>
