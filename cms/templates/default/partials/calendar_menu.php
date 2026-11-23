<?php
/**
 * Кнопка «В календарь» с выбором способа: ссылка Google Календаря или файл .ics.
 *
 * Почему не одно действие: из браузера нельзя открыть «новое событие» в
 * установленном календаре напрямую — Chrome запускает по intent: только
 * activity с категорией BROWSABLE (у календарей её нет), а в iOS нет URL-схемы
 * для Календаря. Поэтому даём два честных варианта:
 * - Google Календарь — на телефоне с приложением Google часто открывается оно;
 * - .ics — iOS открывает системный Календарь, Android скачивает файл для импорта.
 *
 * Ожидает: $meeting. Необязательно: $small (компактная кнопка), $outline (контур).
 */
$small = $small ?? false;
$outline = $outline ?? true;
$google = google_calendar_link($meeting);
$ics = url('meeting_ics', ['id' => (int)$meeting['id']]);
$btnClass = ($small ? 'btn-sm ' : '') . ($outline ? 'btn-outline-success' : 'btn-success');
?>
<div class="dropdown">
    <button class="btn <?= $btnClass ?> cms-btn-icon dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false"
            title="Добавить в календарь" aria-label="Добавить в календарь">
        <?= ui_icon('calendar') ?><span class="cms-btn-label">В календарь</span>
    </button>
    <ul class="dropdown-menu">
        <?php if ($google !== ''): ?>
            <li>
                <a class="dropdown-item" href="<?= e($google) ?>">Google Календарь</a>
            </li>
            <li><hr class="dropdown-divider"></li>
        <?php endif; ?>
        <li>
            <a class="dropdown-item" href="<?= e($ics) ?>"
               title="Скачать файл события и открыть его в календаре">
                Календарь устройства (.ics)
            </a>
        </li>
    </ul>
</div>
