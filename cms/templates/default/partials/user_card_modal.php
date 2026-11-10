<?php
// Модальное окно карточки участника: содержимое подгружается по клику на имя.
?>
<div class="modal fade" id="userCardModal" tabindex="-1" aria-labelledby="userCardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h6 visually-hidden" id="userCardModalTitle">Карточка участника</h2>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body pt-0" data-user-card-body data-url="<?= e(url('user_card')) ?>">
                <div class="text-center text-body-secondary py-4">Загрузка…</div>
            </div>
        </div>
    </div>
</div>
