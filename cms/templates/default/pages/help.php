<div class="row g-4">
    <div class="col-lg-3">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Содержание</div>
            <div class="card-body py-2">
                <nav class="nav nav-pills flex-column small" id="help-nav">
                    <a class="nav-link py-1 px-1 text-body-secondary active" href="#help-intro" data-help="intro">Общие сведения</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-login" data-help="login">Вход и восстановление пароля</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-profile" data-help="profile">Мой профиль</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-home" data-help="home">Главная и календарь</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-meeting" data-help="meeting">Встречи</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-vespers" data-help="vespers">Вечерня и чтецы</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-materials" data-help="materials">Материалы</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-treasury" data-help="treasury">Казна</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-telegram" data-help="telegram">Telegram-бот и рассылка</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-settings" data-help="settings">Настройки</a>
                    <a class="nav-link py-1 px-0 text-body-secondary" href="#help-faq" data-help="faq">Вопросы и ответы</a>
                </nav>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="mb-4">
            <h1 class="h3 mb-2">Справка</h1>
            <p class="text-body-secondary mb-0">
                Сайт координирует деятельность группы катехизации: встречи, посещаемость, вечерни,
                материалы, казна и рассылки. Здесь описано, как пользоваться всеми разделами.
            </p>
        </div>

        <section id="help-intro" class="help-section card shadow-sm mb-4">
            <div class="card-header fw-semibold">Общие сведения</div>
            <div class="card-body">
                <p>
                    Доступ к сайту получают участники группы. У каждого есть учётная запись с ролью,
                    от которой зависит набор доступных разделов и действий.
                </p>
                <table class="table table-sm align-middle">
                    <thead>
                    <tr><th>Роль</th><th>Что доступно</th></tr>
                    </thead>
                    <tbody>
                    <tr><td><span class="badge text-bg-primary">Старший</span></td>
                        <td>Всё: управление пользователями, встречами, местами, настройками, рассылками, казной, чтецами</td></tr>
                    <tr><td><span class="badge text-bg-info text-dark">Помощник</span></td>
                        <td>Управление встречами, местами и материалами, отметка посещаемости, назначение чтецов, рассылки, расходы</td></tr>
                    <tr><td><span class="badge text-bg-secondary">Казначей</span></td>
                        <td>Просмотр казны и добавление расходов</td></tr>
                    <tr><td><span class="badge text-bg-light border">СестраБрат</span></td>
                        <td>Отметка присутствия на встречах, добавление расходов</td></tr>
                    <tr><td><span class="badge text-bg-dark">Гость</span></td>
                        <td>Просмотр общих разделов</td></tr>
                    </tbody>
                </table>
                <p class="mb-0 small text-body-secondary">
                    Дополнительно на встречах используются роли: СтаршийВстречи, СтаршийПоМолитве,
                    ПомощникПоОрганизации, ПомощникПоПитанию — они отмечаются при редактировании встречи.
                </p>
            </div>
        </section>

        <section id="help-login" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Вход и восстановление пароля</div>
            <div class="card-body">
                <h6 class="fw-semibold">Вход</h6>
                <p>
                    На странице входа укажите логин или email и пароль. Если вы не помните логин,
                    попробуйте email — по нему тоже можно войти.
                </p>
                <h6 class="fw-semibold">Восстановление пароля</h6>
                <p class="mb-0">
                    Нажмите «Забыли пароль?» на странице входа, укажите свой email — на него придёт
                    ссылка для сброса. Письмо приходит с адреса, заданного в настройках сайта
                    («Email отправителя писем»); проверяйте папку «Спам», если письма нет.
                </p>
            </div>
        </section>

        <section id="help-profile" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Мой профиль</div>
            <div class="card-body">
                <p>Раздел открывается по имени участника в верхнем меню → «Мой профиль». Здесь можно:</p>
                <ul>
                    <li>
                        <b>Загрузить аватар</b> — фото, которое показывается рядом с именем
                        в днях рождения, контактах, списке участников и чтецов вечерни.
                        Изображение автоматически уменьшается и обрезается по центру до квадрата
                        100×100; убрать его можно кнопкой «Удалить».
                    </li>
                    <li>
                        <b>Изменить данные</b> — логин, имя, email, телефон (и скрыть номер от других
                        участников), ник в Telegram, день рождения.
                    </li>
                    <li>
                        <b>Указать день рождения</b> тремя полями: день, месяц, год. Если год неизвестен —
                        отметьте «без года», тогда возраст выводиться не будет.
                    </li>
                    <li>
                        <b>Персональные данные</b> — телефон, email, дата рождения и Telegram
                        обрабатываются на основании согласия (по ФЗ-152). Пока согласие не дано,
                        эти поля на странице недоступны для редактирования. Дать согласие или
                        отозвать его можно здесь же («Согласиться» / «Отозвать согласие»);
                        после отзыва данные удаляются. Согласие обрабатывается только для
                        коммуникации внутри группы и не передаётся третьим лицам.
                    </li>
                    <li><b>Сменить пароль</b> — укажите текущий и новый пароль (не короче 6 символов).</li>
                    <li>
                        <b>Подключить Telegram-бота</b> — нажмите «Подключить бота», в чате с ботом
                        нажмите «Запустить» (или команду /start). После этого вам будут приходить рассылки.
                    </li>
                    <li><b>Выбрать обращение</b> — брат или сестра; так к вам обращаются на встречах.</li>
                    <li><b>Настроить тему оформления</b> — свою или «по умолчанию» (тему сайта из настроек).</li>
                    <li><b>Настроить размер текста</b> — удобный для чтения на всех страницах.</li>
                </ul>
            </div>
        </section>

        <section id="help-home" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Главная и календарь</div>
            <div class="card-body">
                <p>
                    На <b>главной</b> странице — ближайшие встречи, дни рождения участников и полезные
                    напоминания. Рядом с именами в днях рождения и контактах показываются аватары
                    участников (если они загружены). В разделе <b>«Календарь встреч»</b> — все встречи
                    по датам.
                </p>
                <p class="mb-0">
                    Из списка можно перейти на страницу конкретной встречи: нажать на неё или кнопку
                    «Открыть» (в настройках → «Встречи»).
                </p>
            </div>
        </section>

        <section id="help-meeting" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Встречи</div>
            <div class="card-body">
                <h6 class="fw-semibold">Просмотр и посещаемость</h6>
                <p>
                    На странице встречи указаны дата, время, место (с мини-картой), тема и описание.
                    Отметьте свой статус:
                </p>
                <ul>
                    <li><b>Буду присутствовать</b> — очно;</li>
                    <li><b>Буду онлайн</b> — подключусь дистанционно;</li>
                    <li><b>Буду отсутствовать</b> — не смогу прийти.</li>
                </ul>
                <p>
                    Отметка видна остальным участникам. Статус можно изменить в любой момент.
                    В очных участниках встречи учитываются приглашённые гости и те активные
                    участники, кто отметил «Буду присутствовать»; остальным нужно отметить
                    своё присутствие, иначе они не считаются участниками встречи.
                </p>

                <h6 class="fw-semibold">Чек-лист подготовки</h6>
                <p>
                    Для встречи ведётся список задач по помещению и трапезе. Отметьте выполненные пункты,
                    чтобы организаторам было понятно, что уже сделано.
                </p>

                <h6 class="fw-semibold">Роли на встрече</h6>
                <p>
                    СтаршийВстречи, СтаршийПоМолитве, ПомощникПоОрганизации и ПомощникПоПитанию
                    назначаются при редактировании встречи.
                </p>

                <h6 class="fw-semibold">Старший на встрече</h6>
                <p class="mb-0">
                    В настройках встречи можно указать «Старшего на встрече» — он выбирается
                    из очных участников (включая гостей) и показывается на карточке встречи.
                    Поле можно оставить незаполненным.
                </p>

                <h6 class="fw-semibold">Создание и редактирование</h6>
                <p class="mb-0">
                    Добавить или изменить встречу могут Старший и Помощник: в настройках → «Встречи»
                    или со страницы самой встречи. Для встречи выбирается место, дата, время, тема,
                    описание и метка.
                </p>
            </div>
        </section>

        <section id="help-vespers" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Вечерня и чтецы</div>
            <div class="card-body">
                <p>
                    На странице встречи с вечерней отображается текст службы: слева сам текст,
                    справа — список чтецов.
                </p>
                <ul>
                    <li>В обычные дни текст вечерни загружается из богослужебного сервиса напрямую.</li>
                    <li>
                        Во время постов (в том числе в их будние дни), а также в Страстную седмицу,
                        Пасху и Светлую седмицу вечерня имеет особый чин, поэтому показывается
                        во встроенном окне сервиса.
                    </li>
                </ul>
                <p class="mb-0">
                    Назначать чтецов (кто читает молитвы, псалмы и отрывки Священного Писания) могут
                    Старший и Помощник — в списке чтецов на странице вечерни выбирается участник
                    для каждого места чтения. Назначать можно только очных участников встречи.
                    Рядом с именами чтецов показываются их аватары.
                </p>
            </div>
        </section>

        <section id="help-materials" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Материалы</div>
            <div class="card-body">
                <p>
                    Раздел для изучения: ссылки и тексты, связанные с занятиями группы.
                    Добавлять и редактировать материалы могут Старший и Помощник, все участники — просматривать.
                </p>
            </div>
        </section>

        <section id="help-treasury" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Казна</div>
            <div class="card-body">
                <p>
                    В разделе «Казна» ведётся учёт расходов группы: сумма, назначение, дата и кто добавил.
                    Добавлять расходы могут Старший, Помощник, Казначей и СестраБрат.
                </p>
                <p class="mb-0">
                    Просмотр доступен Казначею; Старший дополнительно видит сводный график. Раздел
                    виден в меню только тем, у кого есть доступ.
                </p>
            </div>
        </section>

        <section id="help-telegram" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Telegram-бот и рассылка</div>
            <div class="card-body">
                <h6 class="fw-semibold">Для участников</h6>
                <p>
                    В «Мой профиль» нажмите «Подключить бота», затем в чате с ботом нажмите «Запустить»
                    (команда /start). После этого сообщения группы будут приходить в этот чат.
                </p>
                <h6 class="fw-semibold">Для Старшего и Помощника</h6>
                <p>
                    Раздел «Рассылка» позволяет отправить сообщение всем участникам, подключённым к боту,
                    или только очным участникам выбранной встречи. Поддерживается простое форматирование:
                    HTML (&lt;b&gt;, &lt;i&gt;, ссылки) или Markdown. Длинные сообщения разбиваются на части
                    автоматически.
                </p>
                <p class="mb-0 small text-body-secondary">
                    Настройка бота (токен, @username и webhook) выполняется один раз в этом же разделе.
                    Токен выдаёт @BotFather при создании бота.
                </p>
            </div>
        </section>

        <section id="help-settings" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Настройки</div>
            <div class="card-body">
                <p>Раздел «Настройки» доступен Старшему и содержит вкладки:</p>
                <ul>
                    <li>
                        <b>Общее</b> — название сайта, тема оформления по умолчанию,
                        email отправителя писем восстановления пароля.
                    </li>
                    <li>
                        <b>Пользователи</b> — учётные записи участников: добавление, изменение роли,
                        активности, пароля и данных. Пароль при редактировании можно не менять,
                        но у активной учётной записи без пароля его обязательно нужно задать.
                        У каждого участника должен оставаться доступ к сайту.
                    </li>
                    <li>
                        <b>Места встреч</b> — адреса и фотографии мест проведения встреч.
                    </li>
                    <li>
                        <b>Встречи</b> — полный список встреч: создание, изменение и удаление.
                    </li>
                </ul>
                <p class="mb-0 small text-body-secondary">
                    Нельзя удалить собственную учётную запись и нельзя оставить сайт без хотя бы одного
                    активного Старшего.
                </p>
            </div>
        </section>

        <section id="help-faq" class="help-section card shadow-sm mb-4 d-none">
            <div class="card-header fw-semibold">Вопросы и ответы</div>
            <div class="card-body">
                <div class="accordion" id="helpFaq">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq1">Не могу войти — что делать?</button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Проверьте, что вводите логин <b>или</b> email и правильный пароль.
                                Если пароль забыт — воспользуйтесь «Забыли пароль?»: на указанный в
                                профиле email придёт ссылка для сброса (проверьте также «Спам»).
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq2">Как изменить день рождения?</button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                В «Мой профиль» в блоке «Редактировать» заполните день, месяц и год.
                                Если год неизвестен — отметьте «без года», тогда возраст не будет
                                выводиться. Изменение сохраняется кнопкой «Сохранить данные».
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq3">Как отметиться на встрече?</button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Откройте страницу встречи и выберите «Буду присутствовать», «Буду онлайн»
                                или «Буду отсутствовать». Статус можно изменить в любой момент.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq4">Как подключиться к боту Telegram?</button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                В «Мой профиль» нажмите «Подключить бота», в открывшемся чате нажмите
                                «Запустить». В профиле появится отметка «Подключено».
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq5">Почему вечерня показывается во встроенном окне?</button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Во время постов (в их будние дни тоже), а также в Страстную седмицу,
                                Пасху и Светлую седмицу вечерня идёт по особому чину (например,
                                Преждеосвященных Даров), поэтому её текст отображается во встроенном
                                окне богослужебного сервиса. В обычные дни текст подставляется
                                прямо на страницу.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq6">Кто может рассылать сообщения?</button>
                        </h2>
                        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Старший и Помощник — в разделе «Рассылка». Получателями будут только
                                те участники, которые подключили бота к своему аккаунту.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq7">Как добавить нового участника?</button>
                        </h2>
                        <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                В «Настройки» → «Пользователи» нажмите «Добавить пользователя»,
                                заполните имя, логин, пароль (не короче 6 символов) и роль.
                                Новому участнику сообщите логин и пароль — он сможет войти и сменить пароль.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq8">Что делать, если не приходит письмо о сбросе пароля?</button>
                        </h2>
                        <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Проверьте папку «Спам» и убедитесь, что у аккаунта указан email в профиле.
                                Если адрес пустой, письмо отправить некуда — обратитесь к Старшему,
                                он изменит email в настройках → «Пользователи».
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq9">Может ли поменяться роль участника?</button>
                        </h2>
                        <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Роль меняет Старший в «Настройки» → «Пользователи» → «Изменить».
                                От роли зависит, какие разделы и действия будут доступны участнику.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq10">Что значит «обращение» брат или сестра?</button>
                        </h2>
                        <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Это форма обращения на встречах. Выберите в «Мой профиль» блок «Обращение» —
                                «Брат» или «Сестра». Значение сохраняется отдельной кнопкой.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq11">Как загрузить или удалить аватар?</button>
                        </h2>
                        <div id="faq11" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                В «Мой профиль» в блоке «Аватар» выберите файл изображения и нажмите
                                «Загрузить аватар». Фото автоматически уменьшится и обрежется по центру
                                до квадрата 100×100, после чего появится рядом с вашим именем.
                                Чтобы убрать аватар, нажмите кнопку «Удалить».
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq12">Зачем нужно согласие на обработку персональных данных?</button>
                        </h2>
                        <div id="faq12" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
                            <div class="accordion-body">
                                Сайт обрабатывает ваши телефон, email, дату рождения и Telegram на основании
                                согласия (ФЗ-152). Эти данные нужны только для коммуникации внутри группы
                                и не передаются третьим лицам. Пока согласие не дано, указанные поля на
                                странице «Мой профиль» недоступны для редактирования.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <p class="text-body-secondary small">
            Не нашли ответ? Обратитесь к Старшему группы — он сможет помочь с любым разделом сайта.
        </p>
    </div>
</div>