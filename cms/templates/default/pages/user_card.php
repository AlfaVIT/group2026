<?php
// Отдельная страница карточки участника — на случай перехода по ссылке без JS.
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <?php partial('user_card', [
                    'cardUser' => $cardUser,
                    'cardAvatar' => $cardAvatar,
                    'cardPhone' => $cardPhone,
                    'cardEmail' => $cardEmail,
                    'cardTelegram' => $cardTelegram,
                    'cardTelegramLink' => $cardTelegramLink,
                    'cardBirthday' => $cardBirthday,
                    'cardAge' => $cardAge,
                    'cardRole' => $cardRole,
                    'cardPdNote' => $cardPdNote,
                ]); ?>
            </div>
        </div>
        <div class="mt-3 text-center">
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('home') ?>">На главную</a>
        </div>
    </div>
</div>
