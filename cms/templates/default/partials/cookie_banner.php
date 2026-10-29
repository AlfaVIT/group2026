<div class="cms-cookie" id="cms-cookie" hidden>
    <div class="cms-cookie-inner">
        <p class="cms-cookie-text">
            Сайт использует файлы cookie — они помогают сайту корректно работать и
            запоминать ваши настройки (например, вход и тему оформления). Продолжая
            пользоваться сайтом, вы соглашаетесь с их использованием.
        </p>
        <button type="button" class="btn btn-primary btn-sm cms-cookie-accept" id="cms-cookie-accept">
            Принимаю
        </button>
    </div>
</div>
<script>
(function () {
    var KEY = 'cms_cookie_consent';
    var box = document.getElementById('cms-cookie');
    if (!box) {
        return;
    }
    var accepted = false;
    try {
        accepted = localStorage.getItem(KEY) === '1';
    } catch (e) {
        accepted = false;
    }
    if (accepted) {
        return;
    }
    box.hidden = false;
    var btn = document.getElementById('cms-cookie-accept');
    if (btn) {
        btn.addEventListener('click', function () {
            try {
                localStorage.setItem(KEY, '1');
            } catch (e) {}
            box.hidden = true;
        });
    }
})();
</script>
