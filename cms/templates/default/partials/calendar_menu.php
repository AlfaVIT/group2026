<?php
/**
 * Кнопка «В календарь» с выбором способа: ссылка Google Календаря или файл .ics.
 *
 * Почему не одно действие: из браузера нельзя открыть «новое событие» в
 * установленном календаре напрямую. Chrome и Яндекс.Браузер не передают
 * календарям ни intent: (нужна категория BROWSABLE, которой у экранов создания
 * события нет), ни обычную https-ссылку; в iOS нет URL-схемы для Календаря.
 * Поэтому даём два честных варианта, и на телефоне первым — тот, что попадает
 * в календарь устройства:
 * - .ics — iOS открывает системный Календарь, Android скачивает файл, который
 *   открывается в календаре при нажатии на него;
 * - Google Календарь — веб-версия события (на десктопе — основной вариант).
 *
 * Ожидает: $meeting. Необязательно: $small (компактная кнопка), $outline (контур).
 */
$small = $small ?? false;
$outline = $outline ?? true;
$google = google_calendar_link($meeting);
$ics = url('meeting_ics', ['id' => (int)$meeting['id']]);
$isAndroid = is_android_client();
$isMobile = $isAndroid || is_ios_client();
$btnClass = ($small ? 'btn-sm ' : '') . ($outline ? 'btn-outline-success' : 'btn-success');
?>
<div class="dropdown">
    <button class="btn <?= $btnClass ?> cms-btn-icon dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false"
            title="Добавить в календарь" aria-label="Добавить в календарь">
        <?= ui_icon('calendar') ?><span class="cms-btn-label">В календарь</span>
    </button>
    <ul class="dropdown-menu">
        <?php if ($isMobile): ?>
            <li>
                <a class="dropdown-item" href="<?= e($ics) ?>"
                   title="Файл события для календаря телефона">
                    Календарь устройства (.ics)
                </a>
            </li>
            <?php if ($isAndroid): ?>
                <li>
                    <span class="dropdown-item-text small text-body-secondary">
                        Файл скачается — откройте его в календаре
                    </span>
                </li>
            <?php endif; ?>
            <?php if ($google !== ''): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e($google) ?>">Google Календарь (веб)</a></li>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($google !== ''): ?>
                <li><a class="dropdown-item" href="<?= e($google) ?>">Google Календарь</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li>
                <a class="dropdown-item" href="<?= e($ics) ?>"
                   title="Скачать файл события и открыть его в календаре">
                    Скачать файл .ics
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>
