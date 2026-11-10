<?php
/**
 * Редактор с панелью форматирования (конспект встречи, материалы).
 * Ожидает: $editorId, $inputId, $fieldName, $html; необязательно $label, $placeholder.
 */
$editorId = $editorId ?? 'noteEditor';
$inputId = $inputId ?? 'noteBody';
$fieldName = $fieldName ?? 'body';
$html = $html ?? '';
$label = $label ?? 'Текст';
$placeholder = $placeholder ?? 'Введите текст…';
?>
<div class="cms-editor">
    <div class="cms-editor-toolbar" role="toolbar" aria-label="Форматирование текста">
        <button type="button" class="cms-editor-btn" data-note-cmd="bold"
                title="Жирный" aria-label="Жирный"><b>B</b></button>
        <button type="button" class="cms-editor-btn" data-note-cmd="italic"
                title="Курсив" aria-label="Курсив"><i>I</i></button>
        <button type="button" class="cms-editor-btn" data-note-cmd="underline"
                title="Подчёркнутый" aria-label="Подчёркнутый"><u>U</u></button>
        <button type="button" class="cms-editor-btn" data-note-cmd="strikeThrough"
                title="Зачёркнутый" aria-label="Зачёркнутый"><s>S</s></button>
        <span class="cms-editor-sep" aria-hidden="true"></span>
        <button type="button" class="cms-editor-btn" data-note-cmd="insertUnorderedList"
                title="Список" aria-label="Маркированный список">&bull;</button>
        <button type="button" class="cms-editor-btn" data-note-cmd="insertOrderedList"
                title="Нумерованный список" aria-label="Нумерованный список">1.</button>
        <button type="button" class="cms-editor-btn" data-note-cmd="formatBlock"
                data-note-arg="h3" title="Заголовок" aria-label="Заголовок">H</button>
        <button type="button" class="cms-editor-btn" data-note-cmd="formatBlock"
                data-note-arg="blockquote" title="Цитата" aria-label="Цитата">&ldquo;&rdquo;</button>
        <span class="cms-editor-sep" aria-hidden="true"></span>
        <button type="button" class="cms-editor-btn" data-note-cmd="link"
                title="Ссылка" aria-label="Вставить ссылку"><?= ui_icon('link', 15) ?></button>
        <button type="button" class="cms-editor-btn" data-note-cmd="removeFormat"
                title="Убрать форматирование" aria-label="Убрать форматирование">&times;</button>
    </div>
    <div class="cms-editor-area" id="<?= e($editorId) ?>" contenteditable="true" role="textbox"
         aria-multiline="true" aria-label="<?= e($label) ?>"
         data-editor-input="<?= e($inputId) ?>"
         data-placeholder="<?= e($placeholder) ?>"><?= $html ?></div>
</div>
<input type="hidden" name="<?= e($fieldName) ?>" id="<?= e($inputId) ?>" value="<?= e($html) ?>">
