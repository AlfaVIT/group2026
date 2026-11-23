<?php
/**
 * Кнопка «В календарь» с выбором способа: ссылка Google Календаря или файл .ics.
 *
 * Почему два варианта, а не один: из браузера нельзя гарантированно открыть
 * «новое событие» в произвольном календаре. Ссылка Google Календаря на Android
 * открывает установленное приложение (app link calendar.google.com), а если его
 * нет — веб-версию; в iOS приложение Google Календаря откроется только если оно
 * установлено, поэтому первым идёт .ics. Файл .ics — для остальных календарей:
 * iOS открывает системный Календарь, Android скачивает файл для импорта.
 *
 * Ожидает: $meeting. Необязательно: $small (компактная кнопка), $outline (контур).
 */
$small = $small ?? false;
$outline = $outline ?? true;
$google = google_calendar_link($meeting);
$ics = url('meeting_ics', ['id' => (int)$meeting['id']]);
$isAndroid = is_android_client();
$isIos = is_ios_client();
$btnClass = ($small ? 'btn-sm ' : '') . ($outline ? 'btn-outline-success' : 'btn-success');
?>
<div class="dropdown">
    <button class="btn <?= $btnClass ?> cms-btn-icon dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-expanded="false"
            title="Добавить в календарь" aria-label="Добавить в календарь">
        <?= ui_icon('calendar') ?><span class="cms-btn-label">В календарь</span>
    </button>
    <ul class="dropdown-menu">
        <?php if ($isIos): ?>
            <li>
                <a class="dropdown-item" href="<?= e($ics) ?>"
                   title="Файл события для календаря телефона">
                    Календарь устройства (.ics)
                </a>
            </li>
            <?php if ($google !== ''): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e($google) ?>">Google Календарь</a></li>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($google !== ''): ?>
                <li><a class="dropdown-item" href="<?= e($google) ?>">Google Календарь</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li>
                <a class="dropdown-item" href="<?= e($ics) ?>"
                   title="Файл события для другого календаря">
                    <?= $isAndroid ? 'Другой календарь (.ics)' : 'Скачать файл .ics' ?>
                </a>
            </li>
            <?php if ($isAndroid): ?>
                <li>
                    <span class="dropdown-item-text small text-body-secondary">
                        Файл скачается — откройте его в календаре, который умеет импорт
                    </span>
                </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
</div>
