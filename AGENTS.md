# Правила проекта group2026

## Главное правило

Любая правка считается завершённой только после того, как она:

1. протестирована;
2. закоммичена и отправлена в git;
3. при необходимости слита в главную ветку `main`;
4. выложена в прод по FTP;
5. проверена на живом сайте.

Инвариант: **рабочая копия == `origin/main` == прод** (`https://group2026.opensite.su/`).
Нельзя оставлять закоммиченные, но не выложенные правки, и нельзя выкладывать то,
чего нет в git.

## Git и ветки

- Главная ветка — `main`, она же продакшн-ветка.
- Если работа идёт в отдельной ветке, перед выкладкой её нужно слить в `main`:
  `git fetch origin`, `git switch main`, `git pull --ff-only`,
  `git merge --no-ff <branch>`, прогнать тесты, `git push origin main`.
- Перед коммитом проверять `git status`: в коммит не должны попадать `ftp.txt`,
  `cms/data/*.sqlite*`, `cms/data/notes/*`, `cms/uploads/*` (они в `.gitignore`).
- Пуш: `git push origin main`. В прод выкладывается только состояние, совпадающее
  с `origin/main`.

## Деплой в прод (FTP)

- Сайт: `https://group2026.opensite.su/`. Корень сайта на FTP = содержимое каталога
  `cms/` (не `cms/` внутри корня, а его файлы).
- FTP: `opensite.myjino.ru:21`, логин `opensite_group2026`, пароль — в `./ftp.txt`
  (файл в git не коммитится и в чат/логи не выводится).
- Выкладывается весь `cms/`, **кроме** `data/` и `uploads/`: серверные БД, кэш,
  конспекты и загруженные файлы перезаписывать нельзя. `data/.htaccess` обновлять можно.
- Файлы, удалённые в git, удалять и на FTP.
- Порядок загрузки: `app/` → `controllers/` → `templates/` → `assets/` →
  `index.php` последним. Так прерванная загрузка не оставит сайт нерабочим.
- FTP Jino временно блокирует IP после нескольких неудачных логинов: пароли не
  перебирать, при блокировке подождать и повторить.

## Проверка после деплоя

- `GET /` → 302 на `index.php?p=login`.
- `GET /?p[]=x` → 302 (не 500); `GET /?p=home&sel[]=x` → 302.
- `GET /?p=logout`, `/?p=telegram_save` → 405.
- `GET /?p=login`, `/?p=consent` → 200 без `Fatal error`/`Parse error`/`Warning` в теле.
- `GET /data/`, `/data/cms.sqlite`, `/mysqlite/` → 403.
- Cookie сессии `PHPSESSID` содержит `secure; HttpOnly; SameSite=Lax`.
- Сверить SHA-256 2–3 ключевых файлов (`index.php`, `app/helpers.php`,
  `controllers/HomeController.php`) локально и по FTP.

## Код

- PHP 8.4, SQLite, UTF-8, переводы строк LF.
- Миграции БД — в `cms/app/installer.php`, версия хранится в `settings.schema_version`.
- Секреты и персональные данные — только вне git.
