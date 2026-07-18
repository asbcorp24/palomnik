@extends('site.layouts.app')

@section('title', 'Справка — Московский паломник')
@section('meta_description', 'Полное руководство по работе с сайтом «Московский паломник» для пользователей и администраторов.')

@php
    $activeSection = request('section') === 'admin' ? 'admin' : 'user';
    $isAdministrator = auth()->check() && auth()->user()->isAdmin();
@endphp

@push('styles')
<style>
    .help-hero {
        padding: 68px 0 44px;
        background:
            radial-gradient(circle at 82% 10%, rgba(176, 138, 62, .18), transparent 30%),
            radial-gradient(circle at 12% 20%, rgba(38, 68, 59, .11), transparent 28%),
            linear-gradient(180deg, #fffdf9, #f7f0e6);
        border-bottom: 1px solid rgba(111, 77, 55, .1);
    }

    .help-role-card,
    .help-panel,
    .help-search-panel {
        background: var(--pm-paper, #fffdf9);
        border: 1px solid rgba(111, 77, 55, .14);
        border-radius: 22px;
        box-shadow: 0 16px 45px rgba(47, 37, 28, .055);
    }

    .help-role-card {
        display: block;
        height: 100%;
        padding: 22px;
        color: inherit;
        text-decoration: none;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }

    .help-role-card:hover,
    .help-role-card.active {
        color: inherit;
        transform: translateY(-2px);
        border-color: rgba(176, 138, 62, .5);
        box-shadow: 0 18px 48px rgba(47, 37, 28, .1);
    }

    .help-role-card.active {
        background: linear-gradient(145deg, #fffdf9, #fbf2df);
    }

    .help-role-icon,
    .help-step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        color: #fff;
        background: #26443b;
    }

    .help-role-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        font-size: 1.25rem;
    }

    .help-search-panel {
        padding: 18px;
    }

    .help-search-input {
        min-height: 50px;
        padding-left: 46px;
        border-radius: 15px;
    }

    .help-search-icon {
        position: absolute;
        top: 50%;
        left: 17px;
        transform: translateY(-50%);
        color: #746c64;
        pointer-events: none;
    }

    .help-toc {
        position: sticky;
        top: 105px;
        padding: 18px;
    }

    .help-toc a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 11px;
        border-radius: 11px;
        color: #5e574f;
        text-decoration: none;
        font-size: .92rem;
    }

    .help-toc a:hover {
        color: #26443b;
        background: rgba(38, 68, 59, .07);
    }

    .help-section {
        scroll-margin-top: 100px;
    }

    .help-section-heading {
        display: flex;
        align-items: center;
        gap: 13px;
        margin-bottom: 18px;
    }

    .help-section-heading .help-role-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        font-size: 1.05rem;
        background: #b08a3e;
    }

    .help-accordion .accordion-item {
        overflow: hidden;
        margin-bottom: 12px;
        border: 1px solid rgba(111, 77, 55, .13);
        border-radius: 16px;
        background: #fffdf9;
    }

    .help-accordion .accordion-button {
        min-height: 62px;
        padding: 17px 20px;
        font-weight: 700;
        color: #2f2a25;
        background: #fffdf9;
        box-shadow: none;
    }

    .help-accordion .accordion-button:not(.collapsed) {
        color: #26443b;
        background: #f8f3e9;
    }

    .help-accordion .accordion-body {
        padding: 4px 20px 22px;
        color: #625b54;
        line-height: 1.72;
    }

    .help-step {
        display: flex;
        gap: 14px;
        margin-bottom: 14px;
    }

    .help-step-number {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        font-size: .82rem;
        font-weight: 700;
    }

    .help-note,
    .help-warning,
    .help-success {
        margin-top: 15px;
        padding: 14px 16px;
        border-radius: 13px;
        font-size: .92rem;
    }

    .help-note {
        color: #3b514b;
        background: rgba(38, 68, 59, .08);
        border-left: 4px solid #26443b;
    }

    .help-warning {
        color: #6f5220;
        background: rgba(176, 138, 62, .12);
        border-left: 4px solid #b08a3e;
    }

    .help-success {
        color: #28543d;
        background: rgba(35, 128, 78, .09);
        border-left: 4px solid #23804e;
    }

    .help-path {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 9px;
        color: #26443b;
        background: rgba(38, 68, 59, .075);
        font-size: .88rem;
        font-weight: 600;
    }

    .help-checklist {
        padding-left: 0;
        list-style: none;
    }

    .help-checklist li {
        position: relative;
        padding-left: 29px;
        margin-bottom: 9px;
    }

    .help-checklist li::before {
        content: '\F26A';
        position: absolute;
        top: 1px;
        left: 0;
        color: #2c7a55;
        font-family: 'bootstrap-icons';
    }

    .help-search-empty {
        display: none;
        padding: 44px 20px;
        text-align: center;
        border: 1px dashed rgba(111, 77, 55, .25);
        border-radius: 18px;
        color: #746c64;
    }

    .help-search-item[hidden],
    .help-section[hidden] {
        display: none !important;
    }

    @media (max-width: 991.98px) {
        .help-toc {
            position: static;
        }
    }

    @media print {
        .site-header,
        .site-footer,
        .mobile-bottom-nav,
        .help-print-hide,
        .help-toc,
        .help-role-switch,
        .help-search-panel {
            display: none !important;
        }

        .help-hero {
            padding: 20px 0;
            background: #fff;
        }

        .accordion-collapse {
            display: block !important;
        }

        .accordion-button::after {
            display: none;
        }

        .help-accordion .accordion-item {
            break-inside: avoid;
            box-shadow: none;
        }
    }
</style>
@endpush

@section('content')
<section class="help-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-3">Центр помощи</div>
                <h1 class="section-title mb-3">Как пользоваться платформой</h1>
                <p class="section-lead mb-0">
                    Полное пошаговое руководство по сайту «Московский паломник»:
                    поиск святынь, карта, маршруты, поездки, личный кабинет, сообщество
                    и управление платформой.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end help-print-hide">
                <button class="btn btn-outline-pm" id="helpPrintButton" type="button">
                    <i class="bi bi-printer me-2"></i>Печать или PDF
                </button>
            </div>
        </div>

        <div class="row g-3 mt-4 help-role-switch">
            <div class="col-md-6">
                <a class="help-role-card {{ $activeSection === 'user' ? 'active' : '' }}" href="{{ route('help', ['section' => 'user']) }}">
                    <div class="d-flex align-items-start gap-3">
                        <span class="help-role-icon"><i class="bi bi-person-heart"></i></span>
                        <span>
                            <strong class="d-block fs-5 mb-1">Пользователю</strong>
                            <span class="text-secondary small">Регистрация, карта, поездки, билеты, профиль и сообщество.</span>
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a class="help-role-card {{ $activeSection === 'admin' ? 'active' : '' }}" href="{{ route('help', ['section' => 'admin']) }}">
                    <div class="d-flex align-items-start gap-3">
                        <span class="help-role-icon"><i class="bi bi-shield-lock"></i></span>
                        <span>
                            <strong class="d-block fs-5 mb-1">Администратору</strong>
                            <span class="text-secondary small">Объекты, маршруты, справочники, пользователи и модерация.</span>
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="help-search-panel mb-4 help-print-hide">
            <div class="row align-items-center g-3">
                <div class="col-lg-7">
                    <div class="position-relative">
                        <i class="bi bi-search help-search-icon"></i>
                        <input
                            class="form-control help-search-input"
                            id="helpSearchInput"
                            type="search"
                            autocomplete="off"
                            placeholder="Найти в справке: билет, маршрут, фото, пользователь..."
                        >
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <button class="btn btn-sm btn-light" id="helpExpandButton" type="button">
                            <i class="bi bi-arrows-expand me-1"></i>Раскрыть всё
                        </button>
                        <button class="btn btn-sm btn-light" id="helpCollapseButton" type="button">
                            <i class="bi bi-arrows-collapse me-1"></i>Свернуть всё
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($activeSection === 'user')
            <div class="row g-4">
                <aside class="col-lg-3 help-print-hide">
                    <div class="help-panel help-toc">
                        <div class="fw-semibold mb-2">Содержание</div>
                        <a href="#user-start"><i class="bi bi-play-circle"></i>Быстрый старт</a>
                        <a href="#user-map"><i class="bi bi-map"></i>Карта и поиск</a>
                        <a href="#user-objects"><i class="bi bi-buildings"></i>Храмы и святыни</a>
                        <a href="#user-routes"><i class="bi bi-signpost-split"></i>Маршруты и календарь</a>
                        <a href="#user-bookings"><i class="bi bi-ticket-perforated"></i>Поездки и билеты</a>
                        <a href="#user-profile"><i class="bi bi-person-circle"></i>Личный кабинет</a>
                        <a href="#user-community"><i class="bi bi-people"></i>Сообщество</a>
                        <a href="#user-safety"><i class="bi bi-shield-check"></i>Безопасность</a>
                        <a href="#user-offline"><i class="bi bi-cloud-arrow-down"></i>Офлайн и приложение</a>
                        <a href="#user-troubleshooting"><i class="bi bi-tools"></i>Решение проблем</a>
                    </div>
                </aside>

                <div class="col-lg-9" id="helpContentRoot">
                    <div class="help-section mb-5" id="user-start" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-play-circle"></i></span>
                            <div>
                                <div class="section-kicker">Начало работы</div>
                                <h2 class="h3 mb-0">Быстрый старт</h2>
                            </div>
                        </div>

                        <div class="accordion help-accordion" id="userStartAccordion">
                            <div class="accordion-item help-search-item" data-help-search="регистрация создать аккаунт пользователь имя email телефон пароль вход">
                                <h3 class="accordion-header" id="userStartRegistrationHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userStartRegistration" type="button">
                                        1. Регистрация нового пользователя
                                    </button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userStartRegistration" data-bs-parent="#userStartAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Нажмите <a href="{{ route('register') }}">«Регистрация»</a> в правой части верхнего меню.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Введите имя, электронную почту, телефон и пароль. Используйте действующий адрес электронной почты.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>Примите правила сервиса и политику обработки персональных данных.</div></div>
                                        <div class="help-step"><span class="help-step-number">4</span><div>После создания аккаунта войдите на сайт и откройте личный кабинет.</div></div>
                                        <div class="help-note">Пароль должен быть известен только владельцу аккаунта. Администратор не видит исходный пароль пользователя.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="вход авторизация выйти забыли пароль аккаунт заблокирован">
                                <h3 class="accordion-header" id="userStartLoginHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userStartLogin" type="button">
                                        2. Вход и выход из аккаунта
                                    </button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userStartLogin" data-bs-parent="#userStartAccordion">
                                    <div class="accordion-body">
                                        <p>Для входа откройте <a href="{{ route('login') }}">страницу авторизации</a>, укажите электронную почту и пароль.</p>
                                        <p>Для выхода откройте меню пользователя в верхней части сайта и нажмите <strong>«Выйти»</strong>. На общедоступном компьютере всегда завершайте сеанс после работы.</p>
                                        <div class="help-warning">Если вход не выполняется, проверьте раскладку клавиатуры, регистр букв и отсутствие пробелов. Отключённый администратором аккаунт не сможет войти до повторной активации.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="навигация меню главная карта объекты маршруты календарь сообщество мобильное меню">
                                <h3 class="accordion-header" id="userStartNavigationHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userStartNavigation" type="button">
                                        3. Основные разделы сайта
                                    </button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userStartNavigation" data-bs-parent="#userStartAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li><strong>Главная</strong> — подборки объектов, маршрутов и ближайших событий.</li>
                                            <li><strong>Карта</strong> — поиск объектов по координатам и построение пути.</li>
                                            <li><strong>Храмы и святыни</strong> — каталог с фильтрами и подробными карточками.</li>
                                            <li><strong>Маршруты</strong> — готовые паломнические программы и расписание поездок.</li>
                                            <li><strong>Календарь</strong> — события, службы, экскурсии и поездки по датам.</li>
                                            <li><strong>Сообщество</strong> — публикации, фотографии и совместные паломничества.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-map" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-map"></i></span>
                            <div><div class="section-kicker">Навигация</div><h2 class="h3 mb-0">Карта и поиск объектов</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userMapAccordion">
                            <div class="accordion-item help-search-item" data-help-search="карта поиск фильтр тип викариатство благочиние святыня объект">
                                <h3 class="accordion-header" id="userMapSearchHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userMapSearch" type="button">Поиск и фильтрация на карте</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userMapSearch" data-bs-parent="#userMapAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <a href="{{ route('map') }}">интерактивную карту</a>. В левой панели доступны поиск по названию и адресу, а также фильтры по типу объекта, викариатству, благочинию и святыне.</p>
                                        <p>Нажмите <strong>«Показать»</strong>, чтобы применить фильтры. Кнопка с крестиком очищает параметры. Нажатие на объект в списке или на маркер перемещает карту и открывает карточку.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="геолокация маршрут от меня пешком автомобиль велосипед автобус общественный транспорт valhalla линия">
                                <h3 class="accordion-header" id="userMapRouteHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userMapRoute" type="button">Построение пути от текущего местоположения</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userMapRoute" data-bs-parent="#userMapAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Разрешите браузеру доступ к геолокации.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Выберите объект на карте и нажмите <strong>«Маршрут отсюда»</strong>.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>Выберите способ передвижения: пешком, автомобиль, велосипед, автобус или общественный транспорт.</div></div>
                                        <div class="help-step"><span class="help-step-number">4</span><div>На карте появится линия пути, расстояние и ориентировочное время.</div></div>
                                        <div class="help-warning">Геолокация обычно доступна только на защищённом HTTPS-сайте или локальном адресе. При запрете доступа маршрут от текущего положения построить нельзя.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="готовый паломнический маршрут точки линия путь карта выбранный маршрут">
                                <h3 class="accordion-header" id="userMapPublishedRouteHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userMapPublishedRoute" type="button">Просмотр готового маршрута на карте</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userMapPublishedRoute" data-bs-parent="#userMapAccordion">
                                    <div class="accordion-body">
                                        <p>Выберите маршрут в фильтре карты либо нажмите <strong>«Показать маршрут на карте»</strong> в его карточке. Порядковые номера показывают последовательность посещения объектов.</p>
                                        <p>Путь рассчитывается между всеми точками. Если сервис дорожной маршрутизации временно недоступен, карта покажет прямую линию между точками, чтобы последовательность маршрута всё равно была видна.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="слои схема спутник историческая карта масштаб полноэкранный режим">
                                <h3 class="accordion-header" id="userMapLayersHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userMapLayers" type="button">Управление картой и слоями</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userMapLayers" data-bs-parent="#userMapAccordion">
                                    <div class="accordion-body">
                                        <p>Колесо мыши и кнопки <strong>+</strong>/<strong>−</strong> меняют масштаб. Кнопка геолокации возвращает карту к вашему положению. Полноэкранный режим удобен при работе с длинным маршрутом.</p>
                                        <p>Спутниковый и исторический слои отображаются только тогда, когда администратор подключил соответствующие источники тайлов.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-objects" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-buildings"></i></span>
                            <div><div class="section-kicker">Каталог</div><h2 class="h3 mb-0">Храмы, монастыри и святыни</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userObjectsAccordion">
                            <div class="accordion-item help-search-item" data-help-search="каталог храм монастырь часовня поиск фильтры карточка">
                                <h3 class="accordion-header" id="userObjectsCatalogHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userObjectsCatalog" type="button">Как найти нужный объект</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userObjectsCatalog" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <a href="{{ route('objects.index') }}">каталог объектов</a>. Используйте строку поиска и фильтры. В карточке отображаются название, тип, адрес, фотография и краткое описание.</p>
                                        <p>Нажмите на карточку, чтобы увидеть историю, святыни, расписание, контакты, парковку, доступность, фотографии, видео, аудиогид и документы.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="избранное список сохранить храм удалить объект">
                                <h3 class="accordion-header" id="userObjectsFavoriteHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userObjectsFavorite" type="button">Добавление объекта в избранное</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userObjectsFavorite" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>В карточке объекта нажмите <strong>«В избранное»</strong>. При наличии нескольких списков выберите нужный. Управление списками находится по пути <span class="help-path">Меню пользователя → Избранное</span>.</p>
                                        <p>Можно создавать тематические подборки: «На выходные», «С детьми», «Храмы центра», «Поездка в августе».</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="отметиться посещение геолокация достижение я здесь">
                                <h3 class="accordion-header" id="userObjectsVisitHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userObjectsVisit" type="button">Подтверждение посещения объекта</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userObjectsVisit" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>Находясь рядом с объектом, нажмите <strong>«Я здесь» → «Отметиться»</strong> и разрешите передачу геолокации. Посещение сохраняется в активности и может учитываться при выдаче достижений.</p>
                                        <div class="help-note">При неточной геолокации посещение может потребовать проверки модератором.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="отзыв рейтинг звезды модерация удалить изменить">
                                <h3 class="accordion-header" id="userObjectsReviewHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userObjectsReview" type="button">Отзывы и оценки</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userObjectsReview" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>Внизу карточки объекта выберите оценку от 1 до 5 звёзд, напишите содержательный отзыв и отправьте его. До публикации материал проходит модерацию.</p>
                                        <p>Не размещайте персональные данные других людей, рекламу, оскорбления и заведомо ложную информацию.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="фото видео загрузить поделиться изображение уменьшение 1920 1080 модерация">
                                <h3 class="accordion-header" id="userObjectsMediaHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userObjectsMedia" type="button">Загрузка фотографии или видео</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userObjectsMedia" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>В блоке <strong>«Поделиться фото»</strong> выберите файл, добавьте подпись и отправьте его. Материал появится после одобрения модератором.</p>
                                        <p>Крупные изображения автоматически уменьшаются пропорционально до установленной администратором границы. По умолчанию максимальный прямоугольник — 1920×1080 пикселей; маленькие изображения не увеличиваются.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="офлайн сохранить карточку без сети кэш">
                                <h3 class="accordion-header" id="userObjectsOfflineHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userObjectsOffline" type="button">Сохранение карточки для работы без сети</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userObjectsOffline" data-bs-parent="#userObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>Нажмите кнопку <strong>«Офлайн»</strong> в карточке объекта. Браузер сохранит страницу и связанные материалы в локальный кэш.</p>
                                        <div class="help-warning">Очистка данных браузера удаляет сохранённые офлайн-материалы. Полная офлайн-карта предназначена для мобильного приложения.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-routes" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-signpost-split"></i></span>
                            <div><div class="section-kicker">Планирование</div><h2 class="h3 mb-0">Маршруты и календарь</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userRoutesAccordion">
                            <div class="accordion-item help-search-item" data-help-search="готовый маршрут категория сложность программа точки стоимость продолжительность">
                                <h3 class="accordion-header" id="userRoutesReadyHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userRoutesReady" type="button">Готовые паломнические маршруты</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userRoutesReady" data-bs-parent="#userRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>В разделе <a href="{{ route('routes.index') }}">«Маршруты»</a> можно искать программы по названию, категории и сложности. Внутри маршрута указаны точки, порядок посещения, программа, продолжительность, ориентировочная стоимость и доступные поездки.</p>
                                        <p>Кнопка <strong>«Показать маршрут на карте»</strong> открывает все точки и линию пути.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="мой маршрут создать редактировать объекты порядок остановка время заметка">
                                <h3 class="accordion-header" id="userRoutesOwnHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userRoutesOwn" type="button">Создание собственного маршрута</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userRoutesOwn" data-bs-parent="#userRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <a href="{{ route('route-plans.index') }}">«Мои маршруты»</a> и нажмите <strong>«Создать маршрут»</strong>. Укажите название, способ передвижения, выберите объекты, задайте порядок и время остановки.</p>
                                        <p>Сохранённый план можно открыть, изменить или удалить. Это личный маршрут — он не публикуется как официальный без участия администратора.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="календарь событие дата фильтр служба экскурсия поездка скачать ics">
                                <h3 class="accordion-header" id="userRoutesCalendarHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userRoutesCalendar" type="button">Календарь событий</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userRoutesCalendar" data-bs-parent="#userRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>В <a href="{{ route('calendar.index') }}">календаре</a> выберите период и тип события. Карточка события содержит дату, время, место, описание, контакты и ссылку на связанный объект, маршрут или поездку.</p>
                                        <p>Файл <strong>ICS</strong> можно добавить в календарь телефона, Outlook, Google Calendar или другую совместимую программу.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-bookings" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-ticket-perforated"></i></span>
                            <div><div class="section-kicker">Организованные поездки</div><h2 class="h3 mb-0">Бронирования и билеты</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userBookingsAccordion">
                            <div class="accordion-item help-search-item" data-help-search="записаться поездка бронирование участники контакт цена места">
                                <h3 class="accordion-header" id="userBookingsCreateHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userBookingsCreate" type="button">Как записаться на поездку</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userBookingsCreate" data-bs-parent="#userBookingsAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Откройте маршрут и выберите поездку со статусом <strong>«Открыта запись»</strong>.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Укажите количество участников и контактные данные.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>Проверьте дату, место встречи, стоимость и отправьте заявку.</div></div>
                                        <div class="help-step"><span class="help-step-number">4</span><div>Следите за статусом в разделе <a href="{{ route('profile.bookings') }}">«Мои билеты»</a>.</div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="статус pending подтвержден отменен завершен возврат билет">
                                <h3 class="accordion-header" id="userBookingsStatusesHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userBookingsStatuses" type="button">Статусы бронирования</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userBookingsStatuses" data-bs-parent="#userBookingsAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li><strong>Ожидает обработки</strong> — заявка создана, решение ещё не принято.</li>
                                            <li><strong>Подтверждено</strong> — место закреплено за участником.</li>
                                            <li><strong>Отменено</strong> — заявка отменена пользователем или организатором.</li>
                                            <li><strong>Завершено</strong> — поездка состоялась.</li>
                                            <li><strong>Возврат</strong> — бронирование закрыто с возвратом средств, если оплата применялась.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="qr код билет показать скачать календарь проверка сканер">
                                <h3 class="accordion-header" id="userBookingsTicketHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userBookingsTicket" type="button">Электронный билет и QR-код</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userBookingsTicket" data-bs-parent="#userBookingsAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте билет в личном кабинете. На входе покажите QR-код сотруднику паломнической службы. Не публикуйте код в социальных сетях и не передавайте посторонним.</p>
                                        <p>Ссылку <strong>«Добавить в календарь»</strong> можно использовать для сохранения даты поездки.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="отмена бронирования освободить место прошедшая поездка">
                                <h3 class="accordion-header" id="userBookingsCancelHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userBookingsCancel" type="button">Отмена записи</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userBookingsCancel" data-bs-parent="#userBookingsAccordion">
                                    <div class="accordion-body">
                                        <p>В разделе <strong>«Мои билеты»</strong> нажмите <strong>«Отменить»</strong>. Место освободится для другого участника. Отменить уже прошедшую или закрытую поездку нельзя.</p>
                                        <div class="help-warning">Условия возврата оплаты определяет организатор поездки. Отмена заявки на сайте сама по себе не гарантирует автоматический возврат денежных средств.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-profile" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-person-circle"></i></span>
                            <div><div class="section-kicker">Персональные данные</div><h2 class="h3 mb-0">Личный кабинет</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userProfileAccordion">
                            <div class="accordion-item help-search-item" data-help-search="личный кабинет статистика последние бронирования посещения достижения">
                                <h3 class="accordion-header" id="userProfileDashboardHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userProfileDashboard" type="button">Главная страница профиля</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userProfileDashboard" data-bs-parent="#userProfileAccordion">
                                    <div class="accordion-body">
                                        <p><a href="{{ auth()->check() ? route('profile.dashboard') : route('login') }}">Личный кабинет</a> показывает краткую статистику, последние бронирования, посещения и полученные достижения.</p>
                                        <p>Из меню доступны настройки, избранное, билеты, достижения, активность, личные маршруты и совместные поездки.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="настройки профиль имя email телефон дата рождения аватар пароль уведомления приватность тема шрифт">
                                <h3 class="accordion-header" id="userProfileSettingsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userProfileSettings" type="button">Настройки профиля и внешнего вида</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userProfileSettings" data-bs-parent="#userProfileAccordion">
                                    <div class="accordion-body">
                                        <p>В настройках можно изменить имя, почту, телефон, дату рождения, аватар и пароль. Для смены пароля потребуется текущий пароль.</p>
                                        <p>Также доступны уведомления, уровень приватности, светлая/тёмная/системная тема и увеличенный размер шрифта.</p>
                                        <div class="help-note">Загруженный аватар также проходит автоматическое пропорциональное уменьшение, если превышает системный предел.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="уведомления колокольчик прочитать все новые сообщения статус модерация">
                                <h3 class="accordion-header" id="userProfileNotificationsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userProfileNotifications" type="button">Уведомления</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userProfileNotifications" data-bs-parent="#userProfileAccordion">
                                    <div class="accordion-body">
                                        <p>Значок колокольчика показывает число непрочитанных уведомлений. В разделе можно открыть сообщение, отметить его прочитанным или отметить прочитанными все сообщения.</p>
                                        <p>Уведомления сообщают о бронированиях, модерации материалов, совместных поездках и других важных изменениях.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="достижения награды баллы прогресс посещения активность">
                                <h3 class="accordion-header" id="userProfileAchievementsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userProfileAchievements" type="button">Достижения и история активности</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userProfileAchievements" data-bs-parent="#userProfileAccordion">
                                    <div class="accordion-body">
                                        <p>Достижения начисляются за посещения, участие в поездках и другую активность. В разделе <strong>«Достижения»</strong> видны условия, баллы и прогресс.</p>
                                        <p>Раздел <strong>«Активность»</strong> объединяет последние посещения, отзывы, публикации и медиаматериалы пользователя.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-community" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-people"></i></span>
                            <div><div class="section-kicker">Общение</div><h2 class="h3 mb-0">Сообщество и совместные поездки</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userCommunityAccordion">
                            <div class="accordion-item help-search-item" data-help-search="сообщество публикация заметка блог черновик отправить модерация">
                                <h3 class="accordion-header" id="userCommunityPostsHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userCommunityPosts" type="button">Создание публикации</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userCommunityPosts" data-bs-parent="#userCommunityAccordion">
                                    <div class="accordion-body">
                                        <p>В разделе <a href="{{ route('community.index') }}">«Сообщество»</a> нажмите <strong>«Новая публикация»</strong>. Укажите заголовок, краткое описание и основной текст.</p>
                                        <p>Публикацию можно сохранить как черновик или отправить на модерацию. После редактирования уже опубликованного материала он может снова потребовать проверки.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="паломничество вместе совместная поездка создать группа организатор дата участники маршрут публичность">
                                <h3 class="accordion-header" id="userCommunityTogetherCreateHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userCommunityTogetherCreate" type="button">Создание совместного паломничества</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userCommunityTogetherCreate" data-bs-parent="#userCommunityAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <a href="{{ route('together.index') }}">«Паломничество вместе»</a> и нажмите <strong>«Создать»</strong>. Укажите название, маршрут, дату, место встречи, максимальное число участников, описание и правила группы.</p>
                                        <p>Организатор рассматривает заявки, принимает или отклоняет участников и общается с группой во встроенном чате.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="вступить заявка группа участник выйти сообщение чат одобрение">
                                <h3 class="accordion-header" id="userCommunityTogetherJoinHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userCommunityTogetherJoin" type="button">Вступление в группу и сообщения</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userCommunityTogetherJoin" data-bs-parent="#userCommunityAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте совместную поездку и нажмите <strong>«Подать заявку»</strong>. После одобрения организатором станет доступен чат участников.</p>
                                        <p>В разделе <a href="{{ auth()->check() ? route('together.my') : route('login') }}">«Мои совместные поездки»</a> отображаются организованные группы и членства. До начала поездки участник может выйти из группы.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="проверенный организатор значок верификация безопасность доверие">
                                <h3 class="accordion-header" id="userCommunityVerifiedHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userCommunityVerified" type="button">Статус проверенного организатора</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userCommunityVerified" data-bs-parent="#userCommunityAccordion">
                                    <div class="accordion-body">
                                        <p>Статус подтверждается администратором после проверки данных. Значок повышает доверие, но не отменяет необходимость самостоятельно оценивать условия поездки, стоимость и контактные данные.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="представитель храма кабинет объект изменения фото билеты сканер">
                                <h3 class="accordion-header" id="userCommunityRepresentativeHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userCommunityRepresentative" type="button">Кабинет представителя храма</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userCommunityRepresentative" data-bs-parent="#userCommunityAccordion">
                                    <div class="accordion-body">
                                        <p>Пользователю с ролью редактора объекта или сотрудника паломнической службы доступен отдельный кабинет. В нём можно предложить изменения карточки закреплённого объекта и загрузить материалы на проверку администратора.</p>
                                        <p>Сотрудникам службы также доступен сканер QR-билетов для регистрации участников поездки.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-safety" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-shield-check"></i></span>
                            <div><div class="section-kicker">Защита пользователей</div><h2 class="h3 mb-0">Жалобы, блокировки и правила</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userSafetyAccordion">
                            <div class="accordion-item help-search-item" data-help-search="жалоба сообщить нарушение пользователь публикация сообщение фото модератор">
                                <h3 class="accordion-header" id="userSafetyReportHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userSafetyReport" type="button">Как сообщить о нарушении</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userSafetyReport" data-bs-parent="#userSafetyAccordion">
                                    <div class="accordion-body">
                                        <p>Используйте кнопку жалобы рядом с пользователем или материалом. Кратко опишите нарушение и приложите факты. Жалоба поступит администраторам.</p>
                                        <p>Не отправляйте повторяющиеся жалобы и не используйте этот инструмент для личных споров.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="заблокировать пользователь разблокировать сообщения скрыть нежелательное общение">
                                <h3 class="accordion-header" id="userSafetyBlockHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userSafetyBlock" type="button">Блокировка пользователя</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userSafetyBlock" data-bs-parent="#userSafetyAccordion">
                                    <div class="accordion-body">
                                        <p>Блокировка ограничивает взаимодействие с нежелательным пользователем. Управление списком находится в настройках профиля, в разделе <strong>«Заблокированные пользователи»</strong>.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="безопасность поездка деньги оплата контакты встреча организатор мошенничество">
                                <h3 class="accordion-header" id="userSafetyTripHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userSafetyTrip" type="button">Безопасность при совместной поездке</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userSafetyTrip" data-bs-parent="#userSafetyAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li>Проверяйте имя организатора, программу, дату и место встречи.</li>
                                            <li>Не переводите деньги по подозрительным ссылкам и неизвестным реквизитам.</li>
                                            <li>Не публикуйте паспортные данные, банковские коды и пароли.</li>
                                            <li>Сообщайте близким маршрут и предполагаемое время возвращения.</li>
                                            <li>При нарушении правил используйте жалобу и прекратите общение.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-offline" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-cloud-arrow-down"></i></span>
                            <div><div class="section-kicker">Мобильное использование</div><h2 class="h3 mb-0">Офлайн-режим и установка сайта</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userOfflineAccordion">
                            <div class="accordion-item help-search-item" data-help-search="установить приложение pwa ярлык телефон домашний экран браузер">
                                <h3 class="accordion-header" id="userOfflineInstallHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userOfflineInstall" type="button">Установка сайта как приложения</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userOfflineInstall" data-bs-parent="#userOfflineAccordion">
                                    <div class="accordion-body">
                                        <p>Поддерживаемый браузер может показать кнопку <strong>«Установить приложение»</strong> внизу сайта или в меню браузера. После установки появится отдельный значок на рабочем столе или главном экране телефона.</p>
                                        <p>Это не отдельная учётная запись: вход выполняется теми же данными, что и на сайте.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="без интернета офлайн сохраненная карточка не открывается кэш сеть">
                                <h3 class="accordion-header" id="userOfflineLimitsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userOfflineLimits" type="button">Ограничения работы без интернета</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userOfflineLimits" data-bs-parent="#userOfflineAccordion">
                                    <div class="accordion-body">
                                        <p>Без сети доступны только ранее сохранённые материалы. Бронирование, сообщения, отзывы, геолокация, синхронизация статусов и загрузка файлов требуют подключения.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="user-troubleshooting" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-tools"></i></span>
                            <div><div class="section-kicker">Поддержка</div><h2 class="h3 mb-0">Частые проблемы</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="userTroubleshootingAccordion">
                            <div class="accordion-item help-search-item" data-help-search="карта пустая не загружается маркеры браузер javascript интернет кэш">
                                <h3 class="accordion-header" id="userTroubleshootingMapHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#userTroubleshootingMap" type="button">Карта не загрузилась или не видно маркеров</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="userTroubleshootingMap" data-bs-parent="#userTroubleshootingAccordion">
                                    <div class="accordion-body">
                                        <ol class="mb-0">
                                            <li>Проверьте интернет и перезагрузите страницу сочетанием Ctrl+F5.</li>
                                            <li>Убедитесь, что JavaScript разрешён.</li>
                                            <li>Сбросьте фильтры карты.</li>
                                            <li>Отключите блокировщик содержимого для сайта.</li>
                                            <li>Попробуйте актуальную версию Chrome, Edge, Firefox или Safari.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="маршрут линия не показывается valhalla точки координаты геолокация">
                                <h3 class="accordion-header" id="userTroubleshootingRouteHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userTroubleshootingRoute" type="button">Не показывается линия маршрута</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userTroubleshootingRoute" data-bs-parent="#userTroubleshootingAccordion">
                                    <div class="accordion-body">
                                        <p>Для готового маршрута нужны минимум две точки с корректными координатами. Для пути от пользователя необходимо разрешение на геолокацию. При недоступности дорожного сервиса готовый маршрут должен отображаться прямыми сегментами.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="файл фото не загружается размер формат jpg png webp видео ошибка">
                                <h3 class="accordion-header" id="userTroubleshootingUploadHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userTroubleshootingUpload" type="button">Не загружается фотография или видео</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userTroubleshootingUpload" data-bs-parent="#userTroubleshootingAccordion">
                                    <div class="accordion-body">
                                        <p>Проверьте формат и исходный размер файла. Для изображений используйте JPG, PNG или WebP. Не закрывайте страницу до завершения отправки.</p>
                                        <p>Если сервер сообщает о превышении размера запроса, файл больше системного ограничения загрузки и должен быть предварительно уменьшен или перекодирован.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="данные не обновились кэш выйти войти браузер">
                                <h3 class="accordion-header" id="userTroubleshootingCacheHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#userTroubleshootingCache" type="button">Изменения не видны на странице</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="userTroubleshootingCache" data-bs-parent="#userTroubleshootingAccordion">
                                    <div class="accordion-body">
                                        <p>Обновите страницу сочетанием Ctrl+F5. Материал, отправленный на модерацию, не отображается публично до одобрения. Статус можно проверить в личном кабинете.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-search-empty" id="helpSearchEmpty">
                        <i class="bi bi-search display-5 d-block mb-3"></i>
                        По вашему запросу ничего не найдено. Попробуйте другое слово.
                    </div>
                </div>
            </div>
        @else
            <div class="row g-4">
                <aside class="col-lg-3 help-print-hide">
                    <div class="help-panel help-toc">
                        <div class="fw-semibold mb-2">Содержание</div>
                        <a href="#admin-start"><i class="bi bi-speedometer2"></i>Вход и обзор</a>
                        <a href="#admin-objects"><i class="bi bi-geo-alt"></i>Объекты и медиа</a>
                        <a href="#admin-directories"><i class="bi bi-journal-bookmark"></i>Справочники</a>
                        <a href="#admin-routes"><i class="bi bi-signpost-split"></i>Маршруты и события</a>
                        <a href="#admin-bookings"><i class="bi bi-ticket-perforated"></i>Билеты и поездки</a>
                        <a href="#admin-service"><i class="bi bi-building-check"></i>Представители храмов</a>
                        <a href="#admin-moderation"><i class="bi bi-check2-square"></i>Модерация</a>
                        <a href="#admin-users"><i class="bi bi-people"></i>Пользователи и роли</a>
                        <a href="#admin-safety"><i class="bi bi-shield-exclamation"></i>Безопасность</a>
                        <a href="#admin-images"><i class="bi bi-image"></i>Изображения</a>
                        <a href="#admin-workflow"><i class="bi bi-diagram-3"></i>Рабочий порядок</a>
                    </div>
                </aside>

                <div class="col-lg-9" id="helpContentRoot">
                    <div class="help-panel p-4 mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <div class="section-kicker mb-1">Доступ администратора</div>
                                <h2 class="h4 mb-1">Руководство по управлению платформой</h2>
                                <div class="text-secondary small">Операции доступны только пользователям с ролью администратора или главного администратора.</div>
                            </div>

                            @if($isAdministrator)
                                <a class="btn btn-pm-green" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-speedometer2 me-2"></i>Открыть админ-панель
                                </a>
                            @else
                                <a class="btn btn-outline-pm" href="{{ route('admin.login') }}">
                                    <i class="bi bi-shield-lock me-2"></i>Вход администратора
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-start" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-speedometer2"></i></span>
                            <div><div class="section-kicker">Начало работы</div><h2 class="h3 mb-0">Вход и обзор системы</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminStartAccordion">
                            <div class="accordion-item help-search-item" data-help-search="админ вход панель логин пароль выход безопасность">
                                <h3 class="accordion-header" id="adminStartLoginHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminStartLogin" type="button">Вход в административную панель</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminStartLogin" data-bs-parent="#adminStartAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Откройте <a href="{{ route('admin.login') }}">страницу входа администратора</a>.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Введите данные активной учётной записи с ролью администратора или главного администратора.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>После входа откроется обзор платформы и боковое меню управления.</div></div>
                                        <div class="help-warning">Не передавайте административный пароль. После работы на чужом компьютере обязательно нажмите кнопку выхода в верхней панели.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="обзор статистика dashboard показатели быстрые ссылки уведомления">
                                <h3 class="accordion-header" id="adminStartDashboardHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminStartDashboard" type="button">Что находится на странице «Обзор»</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminStartDashboard" data-bs-parent="#adminStartAccordion">
                                    <div class="accordion-body">
                                        <p>Обзор показывает основные показатели платформы и ссылки на рабочие разделы. Используйте его для ежедневной проверки новых материалов, заявок, жалоб и изменений от представителей храмов.</p>
                                        <p>Значок колокольчика в верхней панели ведёт к системным уведомлениям администратора.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="боковое меню мобильный экран открыть сайт новый таб">
                                <h3 class="accordion-header" id="adminStartNavigationHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminStartNavigation" type="button">Навигация по админ-панели</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminStartNavigation" data-bs-parent="#adminStartAccordion">
                                    <div class="accordion-body">
                                        <p>Все модули собраны в левом меню по группам: карта и объекты, маршруты и события, геймификация, сообщество и справочники.</p>
                                        <p>На узком экране откройте меню кнопкой с тремя полосами. Ссылка <strong>«Открыть сайт»</strong> показывает публичную часть в новой вкладке.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-objects" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-geo-alt"></i></span>
                            <div><div class="section-kicker">Каталог</div><h2 class="h3 mb-0">Храмы, объекты и медиаматериалы</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminObjectsAccordion">
                            <div class="accordion-item help-search-item" data-help-search="создать храм объект название slug тип викариатство благочиние адрес координаты описание история">
                                <h3 class="accordion-header" id="adminObjectsCreateHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminObjectsCreate" type="button">Создание нового объекта</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminObjectsCreate" data-bs-parent="#adminObjectsAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Перейдите <span class="help-path">Храмы и объекты → Добавить объект</span>.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Заполните название, тип, викариатство, благочиние, адрес и координаты.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>Добавьте краткое описание, полный текст, историю, расписание, контакты, парковку и сведения о доступности.</div></div>
                                        <div class="help-step"><span class="help-step-number">4</span><div>Свяжите объект со святынями и сохраните запись.</div></div>
                                        <div class="help-note">Координаты обязательны для корректного маркера и построения маршрутов. Формат: широта и долгота в десятичном виде.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="публикация черновик опубликован скрыть архив удалить объект">
                                <h3 class="accordion-header" id="adminObjectsPublishHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminObjectsPublish" type="button">Публикация, скрытие и удаление</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminObjectsPublish" data-bs-parent="#adminObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>Перед публикацией проверьте обязательные данные, координаты, тексты и изображения. Неопубликованный объект не должен отображаться в публичном каталоге.</p>
                                        <p>Удаление используйте только при ошибочно созданной записи. Для временного скрытия предпочтительнее снять публикацию или использовать предусмотренный архивный статус.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="медиа фото видео аудио документ загрузить обложка порядок внешняя ссылка заменить удалить">
                                <h3 class="accordion-header" id="adminObjectsMediaHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminObjectsMedia" type="button">Фотографии, видео, аудио и документы</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminObjectsMedia" data-bs-parent="#adminObjectsAccordion">
                                    <div class="accordion-body">
                                        <p>В форме объекта загрузите до 20 файлов за одну операцию либо укажите внешнюю ссылку. Система определит тип материала по MIME-типу файла.</p>
                                        <p>Первое изображение становится обложкой, если обложка ещё не назначена. В редакторе медиаматериала можно изменить название, описание, порядок, заменить файл и назначить изображение обложкой.</p>
                                        <div class="help-warning">Перед удалением убедитесь, что материал не является единственной подходящей обложкой. После удаления система попытается назначить следующую фотографию.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="проверить публичную карточку сайт карта маршрут кэш">
                                <h3 class="accordion-header" id="adminObjectsCheckHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminObjectsCheck" type="button">Проверка объекта после сохранения</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminObjectsCheck" data-bs-parent="#adminObjectsAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li>Откройте публичную карточку объекта.</li>
                                            <li>Проверьте обложку, святыни, галерею, расписание и контакты.</li>
                                            <li>Убедитесь, что маркер находится в правильной точке карты.</li>
                                            <li>Проверьте построение пути до объекта.</li>
                                            <li>На мобильном экране убедитесь, что карточка не выходит за границы.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-directories" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-journal-bookmark"></i></span>
                            <div><div class="section-kicker">Базовые данные</div><h2 class="h3 mb-0">Справочники</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminDirectoriesAccordion">
                            <div class="accordion-item help-search-item" data-help-search="тип объекта маркер цвет иконка порядок справочник">
                                <h3 class="accordion-header" id="adminDirectoriesTypesHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminDirectoriesTypes" type="button">Типы объектов</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminDirectoriesTypes" data-bs-parent="#adminDirectoriesAccordion">
                                    <div class="accordion-body">
                                        <p>Типы используются в фильтрах каталога и для оформления маркеров карты. Задайте понятное название, уникальный адресный идентификатор, цвет маркера, иконку и порядок сортировки.</p>
                                        <p>Удалить тип, связанный с объектами, нельзя. Сначала переназначьте связанные объекты.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="викариатство благочиние иерархия справочник связь объект">
                                <h3 class="accordion-header" id="adminDirectoriesChurchHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminDirectoriesChurch" type="button">Викариатства и благочиния</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminDirectoriesChurch" data-bs-parent="#adminDirectoriesAccordion">
                                    <div class="accordion-body">
                                        <p>Сначала создайте викариатство, затем благочиние и привяжите его к викариатству. После этого административные единицы можно назначать объектам.</p>
                                        <p>Система защищает используемые записи от случайного удаления.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="святыня икона мощи источник крест фото описание тип загрузить">
                                <h3 class="accordion-header" id="adminDirectoriesSanctitiesHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminDirectoriesSanctities" type="button">Святыни и их фотографии</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminDirectoriesSanctities" data-bs-parent="#adminDirectoriesAccordion">
                                    <div class="accordion-body">
                                        <p>Для святыни задайте название, тип и описание. Загрузите фотографию в JPG, PNG или WebP. Затем привяжите святыню к одному или нескольким объектам.</p>
                                        <p>В карточке объекта фотография, описание святыни и примечание связи отображаются отдельным блоком.</p>
                                        <div class="help-warning">Удаление фотографии необратимо. При замене старый файл удаляется после успешной загрузки нового.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-routes" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-signpost-split"></i></span>
                            <div><div class="section-kicker">Планирование поездок</div><h2 class="h3 mb-0">Маршруты, расписание и календарь</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminRoutesAccordion">
                            <div class="accordion-item help-search-item" data-help-search="создать маршрут название категория сложность программа объекты порядок карта публикация">
                                <h3 class="accordion-header" id="adminRoutesCreateHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminRoutesCreate" type="button">Создание паломнического маршрута</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminRoutesCreate" data-bs-parent="#adminRoutesAccordion">
                                    <div class="accordion-body">
                                        <div class="help-step"><span class="help-step-number">1</span><div>Откройте <span class="help-path">Маршруты → Создать</span>.</div></div>
                                        <div class="help-step"><span class="help-step-number">2</span><div>Укажите название, категорию, сложность, продолжительность, стоимость, краткое описание, полный текст и программу.</div></div>
                                        <div class="help-step"><span class="help-step-number">3</span><div>Выберите объекты в требуемом порядке. Порядок определяет нумерацию остановок и линию на карте.</div></div>
                                        <div class="help-step"><span class="help-step-number">4</span><div>Опубликуйте маршрут и проверьте его публичную карточку и карту.</div></div>
                                        <div class="help-note">Для линии маршрута нужны минимум два объекта с корректными координатами.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="расписание поездка trip дата начало окончание место встречи вместимость цена статус">
                                <h3 class="accordion-header" id="adminRoutesTripsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminRoutesTrips" type="button">Расписание организованных поездок</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminRoutesTrips" data-bs-parent="#adminRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>Создайте поездку на основе маршрута. Укажите дату начала и окончания, место встречи, вместимость, цену, примечание и статус.</p>
                                        <ul class="help-checklist mb-0">
                                            <li><strong>Запланирована</strong> — подготовительный этап.</li>
                                            <li><strong>Открыта запись</strong> — пользователи могут бронировать места.</li>
                                            <li><strong>Запись закрыта</strong> — новые заявки не принимаются.</li>
                                            <li><strong>Отменена</strong> — поездка не состоится.</li>
                                            <li><strong>Завершена</strong> — поездка проведена.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="календарь событие создать дата объект маршрут поездка публикация адрес координаты вместимость регистрация">
                                <h3 class="accordion-header" id="adminRoutesCalendarHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminRoutesCalendar" type="button">Календарь событий</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminRoutesCalendar" data-bs-parent="#adminRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>Событие можно связать с объектом, маршрутом или поездкой. Заполните дату, тип, место, описание, координаты, контакты, вместимость и ссылку регистрации.</p>
                                        <p>Опубликованное событие появится в публичном календаре. Для событий на весь день включите соответствующий переключатель.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="маршрут на карте линия valhalla прямая точки не отображается диагностика">
                                <h3 class="accordion-header" id="adminRoutesMapHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminRoutesMap" type="button">Проверка маршрута на карте</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminRoutesMap" data-bs-parent="#adminRoutesAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте публичную карточку и нажмите <strong>«Показать маршрут на карте»</strong>. Проверьте порядок нумерованных точек и линию пути.</p>
                                        <p>При ошибке проверьте публикацию маршрута, число объектов, координаты каждой точки и доступность сервиса маршрутизации.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-bookings" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-ticket-perforated"></i></span>
                            <div><div class="section-kicker">Участники поездок</div><h2 class="h3 mb-0">Бронирования и QR-билеты</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminBookingsAccordion">
                            <div class="accordion-item help-search-item" data-help-search="бронирования заявки статус участники оплата подтвердить отменить завершить возврат">
                                <h3 class="accordion-header" id="adminBookingsModerateHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminBookingsModerate" type="button">Обработка бронирований</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminBookingsModerate" data-bs-parent="#adminBookingsAccordion">
                                    <div class="accordion-body">
                                        <p>В разделе <span class="help-path">Бронирования и билеты</span> фильтруйте заявки по статусу и поездке. Перед подтверждением проверьте число участников, доступную вместимость и контактные данные.</p>
                                        <p>Изменяйте статус последовательно и не подтверждайте больше участников, чем позволяет вместимость поездки.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="сканер qr билет проверить код регистрация участника check in камера">
                                <h3 class="accordion-header" id="adminBookingsScannerHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminBookingsScanner" type="button">Проверка QR-билета</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminBookingsScanner" data-bs-parent="#adminBookingsAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <strong>«Сканер QR-билетов»</strong>. Разрешите доступ к камере, наведите её на код или выполните поиск по коду вручную.</p>
                                        <p>Перед регистрацией участника сравните имя, поездку и статус билета. Повторно использованный либо отменённый билет не следует принимать.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-service" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-building-check"></i></span>
                            <div><div class="section-kicker">Делегирование</div><h2 class="h3 mb-0">Представители храмов и заявки на изменения</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminServiceAccordion">
                            <div class="accordion-item help-search-item" data-help-search="представитель храм назначить пользователь объект статус подтверждение права">
                                <h3 class="accordion-header" id="adminServiceRepresentativeHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminServiceRepresentative" type="button">Назначение представителя объекта</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminServiceRepresentative" data-bs-parent="#adminServiceAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте <strong>«Представители храмов»</strong>, выберите пользователя и объект, затем назначьте статус. Пользователь должен иметь подходящую роль и активную учётную запись.</p>
                                        <p>Не выдавайте доступ к объекту без проверки личности и полномочий представителя.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="изменения от храмов заявка сравнить одобрить отклонить карточка медиа">
                                <h3 class="accordion-header" id="adminServiceReviewHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminServiceReview" type="button">Проверка предложенных изменений</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminServiceReview" data-bs-parent="#adminServiceAccordion">
                                    <div class="accordion-body">
                                        <p>В разделе <strong>«Изменения от храмов»</strong> отдельно рассматриваются текстовые изменения карточек и новые медиаматериалы.</p>
                                        <ul class="help-checklist mb-0">
                                            <li>Сравните новые данные с текущей карточкой.</li>
                                            <li>Проверьте контакты, расписание и адрес.</li>
                                            <li>Откройте изображение в полном размере.</li>
                                            <li>Одобрите только достоверные и корректные материалы.</li>
                                            <li>При отклонении укажите понятную причину.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-moderation" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-check2-square"></i></span>
                            <div><div class="section-kicker">Контроль публикаций</div><h2 class="h3 mb-0">Модерация контента</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminModerationAccordion">
                            <div class="accordion-item help-search-item" data-help-search="отзывы модерация одобрить отклонить удалить рейтинг оскорбление реклама">
                                <h3 class="accordion-header" id="adminModerationReviewsHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminModerationReviews" type="button">Отзывы</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminModerationReviews" data-bs-parent="#adminModerationAccordion">
                                    <div class="accordion-body">
                                        <p>Проверьте соответствие отзыва объекту, отсутствие рекламы, персональных данных, оскорблений и запрещённого содержания. Одобряйте содержательные отзывы, отклоняйте нарушения с указанием причины.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="фото видео медиа модерация авторские права качество содержание">
                                <h3 class="accordion-header" id="adminModerationMediaHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminModerationMedia" type="button">Пользовательские фотографии и видео</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminModerationMedia" data-bs-parent="#adminModerationAccordion">
                                    <div class="accordion-body">
                                        <p>Проверьте соответствие объекту, качество, отсутствие чужих персональных данных и недопустимого содержания. Убедитесь, что материал не нарушает авторские права.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="блог публикация заметка текст модерация черновик pending опубликован">
                                <h3 class="accordion-header" id="adminModerationPostsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminModerationPosts" type="button">Блог и заметки</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminModerationPosts" data-bs-parent="#adminModerationAccordion">
                                    <div class="accordion-body">
                                        <p>Проверьте заголовок, текст, факты, оформление и связанные материалы. После одобрения запись публикуется в сообществе. Удаление используйте при серьёзном нарушении или по обоснованному запросу автора.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="посещения геолокация подтверждение отклонение достижение">
                                <h3 class="accordion-header" id="adminModerationVisitsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminModerationVisits" type="button">Посещения</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminModerationVisits" data-bs-parent="#adminModerationAccordion">
                                    <div class="accordion-body">
                                        <p>Проверяйте сомнительные отметки с учётом координат объекта, времени и точности геолокации. Подтверждённые посещения могут запускать выдачу достижений.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="достижения квесты условие баллы активность включить выключить">
                                <h3 class="accordion-header" id="adminModerationAchievementsHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminModerationAchievements" type="button">Достижения и квесты</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminModerationAchievements" data-bs-parent="#adminModerationAccordion">
                                    <div class="accordion-body">
                                        <p>Задайте название, категорию, уровень значка, баллы, тип условия, требуемое значение, описание и иконку. Не меняйте условия уже массово выданного достижения без проверки последствий.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-users" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-people"></i></span>
                            <div><div class="section-kicker">Учётные записи</div><h2 class="h3 mb-0">Пользователи и роли</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminUsersAccordion">
                            <div class="accordion-item help-search-item" data-help-search="пользователи поиск роль активность статистика карточка профиль">
                                <h3 class="accordion-header" id="adminUsersListHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminUsersList" type="button">Поиск и просмотр пользователя</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminUsersList" data-bs-parent="#adminUsersAccordion">
                                    <div class="accordion-body">
                                        <p>В списке можно искать по имени, электронной почте и телефону, а также фильтровать по роли и активности. Карточка пользователя показывает статистику, посещения, бронирования, достижения и назначения представителем.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="роли паломник редактор объектов служба модератор администратор главный администратор права">
                                <h3 class="accordion-header" id="adminUsersRolesHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminUsersRoles" type="button">Назначение ролей</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminUsersRoles" data-bs-parent="#adminUsersAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li><strong>Паломник</strong> — обычный пользователь сайта.</li>
                                            <li><strong>Редактор объектов</strong> — предлагает изменения закреплённых объектов.</li>
                                            <li><strong>Паломническая служба</strong> — управляет объектами в пределах полномочий и проверяет билеты.</li>
                                            <li><strong>Модератор</strong> — роль для контентной работы в предусмотренных интерфейсах.</li>
                                            <li><strong>Администратор</strong> — доступ к административной панели.</li>
                                            <li><strong>Главный администратор</strong> — максимальный административный уровень.</li>
                                        </ul>
                                        <div class="help-warning">Назначайте административные роли только проверенным сотрудникам. После изменения роли попросите пользователя заново войти в систему.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="отключить активировать пользователь заблокировать аккаунт сам себя администратор">
                                <h3 class="accordion-header" id="adminUsersActiveHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminUsersActive" type="button">Отключение и повторная активация аккаунта</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminUsersActive" data-bs-parent="#adminUsersAccordion">
                                    <div class="accordion-body">
                                        <p>Снимите флаг активности, чтобы запретить вход без удаления данных. После повторного включения пользователь снова сможет авторизоваться.</p>
                                        <p>Система не позволяет администратору отключить собственную текущую учётную запись.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="проверенный организатор верификация подтвердить снять статус">
                                <h3 class="accordion-header" id="adminUsersVerifiedHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminUsersVerified" type="button">Проверенный организатор</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminUsersVerified" data-bs-parent="#adminUsersAccordion">
                                    <div class="accordion-body">
                                        <p>Присваивайте статус только после проверки личности, контактов и оснований для организации групп. При появлении обоснованных жалоб статус можно снять.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-safety" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-shield-exclamation"></i></span>
                            <div><div class="section-kicker">Доверие и порядок</div><h2 class="h3 mb-0">Жалобы и совместные паломничества</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminSafetyAccordion">
                            <div class="accordion-item help-search-item" data-help-search="жалобы безопасность рассмотреть статус пользователь материал причина решение">
                                <h3 class="accordion-header" id="adminSafetyReportsHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminSafetyReports" type="button">Рассмотрение жалоб</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminSafetyReports" data-bs-parent="#adminSafetyAccordion">
                                    <div class="accordion-body">
                                        <p>Откройте жалобу, изучите автора, объект жалобы, описание и контекст. Зафиксируйте результат рассмотрения и измените статус. При необходимости ограничьте аккаунт или удалите нарушающий материал.</p>
                                        <p>Решение должно быть основано на правилах сервиса, а не на личных предпочтениях модератора.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="паломничество вместе группы модерировать скрыть удалить статус участники организатор">
                                <h3 class="accordion-header" id="adminSafetyTogetherHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminSafetyTogether" type="button">Контроль совместных поездок</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminSafetyTogether" data-bs-parent="#adminSafetyAccordion">
                                    <div class="accordion-body">
                                        <p>Проверяйте публичные описания групп, даты, места встречи, условия участия и жалобы. При нарушении правил измените статус или удалите запись.</p>
                                        <div class="help-warning">Перед удалением группы оцените влияние на участников и сохраните необходимые сведения для разбора обращения.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-images" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-image"></i></span>
                            <div><div class="section-kicker">Медиафайлы</div><h2 class="h3 mb-0">Автоматическое уменьшение изображений</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminImagesAccordion">
                            <div class="accordion-item help-search-item" data-help-search="изображение размер 1920 1080 пропорционально env качество jpg webp png">
                                <h3 class="accordion-header" id="adminImagesResizeHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminImagesResize" type="button">Как работает уменьшение</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminImagesResize" data-bs-parent="#adminImagesAccordion">
                                    <div class="accordion-body">
                                        <p>Все загружаемые изображения проверяются до сохранения. Файл, превышающий максимальную ширину или высоту, уменьшается пропорционально и не обрезается. Маленькие изображения не увеличиваются.</p>
                                        <p>По умолчанию используется максимальный прямоугольник <strong>1920×1080</strong>. Например, фотография 3000×2000 станет 1620×1080, а 2000×3000 — 720×1080.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="env IMAGE_MAX_WIDTH IMAGE_MAX_HEIGHT IMAGE_RESIZE_ENABLED качество jpeg webp compression">
                                <h3 class="accordion-header" id="adminImagesEnvHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminImagesEnv" type="button">Настройки размера и качества</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminImagesEnv" data-bs-parent="#adminImagesAccordion">
                                    <div class="accordion-body">
                                        <pre class="bg-dark text-light rounded-4 p-3 mb-3"><code>IMAGE_RESIZE_ENABLED=true
IMAGE_MAX_WIDTH=1920
IMAGE_MAX_HEIGHT=1080
IMAGE_JPEG_QUALITY=85
IMAGE_WEBP_QUALITY=85
IMAGE_PNG_COMPRESSION=8</code></pre>
                                        <p>После изменения параметров необходимо очистить кэш конфигурации Laravel. Эта операция относится к техническому обслуживанию и должна выполняться ответственным специалистом.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="gd расширение php ошибка обработка фото webp png jpeg">
                                <h3 class="accordion-header" id="adminImagesGdHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminImagesGd" type="button">Требование к серверу</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminImagesGd" data-bs-parent="#adminImagesAccordion">
                                    <div class="accordion-body">
                                        <p>Для уменьшения изображений должно быть включено расширение PHP GD с поддержкой используемых форматов. При отсутствии нужной функции загрузка крупного файла завершится понятной ошибкой обработки.</p>
                                        <p>Анимированные GIF не уменьшаются, чтобы не потерять анимацию.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-section mb-5" id="admin-workflow" data-help-section>
                        <div class="help-section-heading">
                            <span class="help-role-icon"><i class="bi bi-diagram-3"></i></span>
                            <div><div class="section-kicker">Регламент</div><h2 class="h3 mb-0">Рекомендуемый рабочий порядок</h2></div>
                        </div>

                        <div class="accordion help-accordion" id="adminWorkflowAccordion">
                            <div class="accordion-item help-search-item" data-help-search="ежедневно проверка модерация жалобы заявки бронирования уведомления">
                                <h3 class="accordion-header" id="adminWorkflowDailyHeading">
                                    <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#adminWorkflowDaily" type="button">Ежедневная проверка</button>
                                </h3>
                                <div class="accordion-collapse collapse show" id="adminWorkflowDaily" data-bs-parent="#adminWorkflowAccordion">
                                    <div class="accordion-body">
                                        <ol class="mb-0">
                                            <li>Проверить уведомления и новые жалобы.</li>
                                            <li>Рассмотреть отзывы, публикации, фотографии и посещения.</li>
                                            <li>Проверить изменения и медиа от представителей храмов.</li>
                                            <li>Обработать бронирования ближайших поездок.</li>
                                            <li>Проверить совместные паломничества с новыми жалобами.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="перед публикацией чеклист проверить объект маршрут событие фото координаты мобильный">
                                <h3 class="accordion-header" id="adminWorkflowPublishHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminWorkflowPublish" type="button">Проверка перед публикацией</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminWorkflowPublish" data-bs-parent="#adminWorkflowAccordion">
                                    <div class="accordion-body">
                                        <ul class="help-checklist mb-0">
                                            <li>Название и тексты проверены на ошибки.</li>
                                            <li>Адрес, телефон, электронная почта и сайт актуальны.</li>
                                            <li>Координаты проверены на карте.</li>
                                            <li>Изображения открываются и имеют правильную ориентацию.</li>
                                            <li>Связанные объекты, святыни, маршрут и события указаны верно.</li>
                                            <li>Публичная страница проверена на компьютере и телефоне.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="удаление резервная копия необратимо данные пользователь медиа">
                                <h3 class="accordion-header" id="adminWorkflowDeleteHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminWorkflowDelete" type="button">Осторожное удаление данных</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminWorkflowDelete" data-bs-parent="#adminWorkflowAccordion">
                                    <div class="accordion-body">
                                        <p>Перед удалением проверьте связи записи. Для временного скрытия используйте статусы публикации и активности. Физическое удаление файла из хранилища может быть необратимым.</p>
                                        <div class="help-warning">Перед массовыми изменениями и удалениями должна существовать актуальная резервная копия базы данных и каталога пользовательских файлов.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item help-search-item" data-help-search="после обновления кэш artisan optimize clear view cache ошибка старая страница">
                                <h3 class="accordion-header" id="adminWorkflowCacheHeading">
                                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#adminWorkflowCache" type="button">Сайт показывает старую версию после обновления</button>
                                </h3>
                                <div class="accordion-collapse collapse" id="adminWorkflowCache" data-bs-parent="#adminWorkflowAccordion">
                                    <div class="accordion-body">
                                        <p>Сначала выполните принудительное обновление браузера. Если проблема сохраняется после развёртывания новой версии, ответственному специалисту нужно очистить кэши Laravel и заново скомпилировать Blade-шаблоны.</p>
                                        <pre class="bg-dark text-light rounded-4 p-3 mb-0"><code>php artisan optimize:clear
php artisan view:clear
php artisan view:cache</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="help-search-empty" id="helpSearchEmpty">
                        <i class="bi bi-search display-5 d-block mb-3"></i>
                        По вашему запросу ничего не найдено. Попробуйте другое слово.
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const searchInput = document.getElementById('helpSearchInput');
    const emptyState = document.getElementById('helpSearchEmpty');
    const root = document.getElementById('helpContentRoot');

    function normalize(value) {
        return String(value || '')
            .toLocaleLowerCase('ru-RU')
            .replace(/ё/g, 'е')
            .trim();
    }

    function filterHelp() {
        if (!root || !searchInput) return;

        const query = normalize(searchInput.value);
        const items = Array.from(root.querySelectorAll('.help-search-item'));
        let visibleCount = 0;

        items.forEach(item => {
            const searchText = normalize(`${item.dataset.helpSearch || ''} ${item.textContent || ''}`);
            const visible = query === '' || searchText.includes(query);
            item.hidden = !visible;

            if (visible) {
                visibleCount++;

                if (query !== '') {
                    const collapseElement = item.querySelector('.accordion-collapse');
                    if (collapseElement) {
                        bootstrap.Collapse.getOrCreateInstance(collapseElement, {toggle: false}).show();
                    }
                }
            }
        });

        root.querySelectorAll('[data-help-section]').forEach(section => {
            section.hidden = !section.querySelector('.help-search-item:not([hidden])');
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    function setAllAccordions(open) {
        document.querySelectorAll('#helpContentRoot .accordion-collapse').forEach(element => {
            const instance = bootstrap.Collapse.getOrCreateInstance(element, {toggle: false});
            open ? instance.show() : instance.hide();
        });
    }

    searchInput?.addEventListener('input', filterHelp);
    document.getElementById('helpExpandButton')?.addEventListener('click', () => setAllAccordions(true));
    document.getElementById('helpCollapseButton')?.addEventListener('click', () => setAllAccordions(false));
    document.getElementById('helpPrintButton')?.addEventListener('click', () => window.print());
})();
</script>
@endpush
