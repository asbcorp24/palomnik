<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#26443b">
    <meta name="description" content="@yield('meta_description', 'Московский паломник — храмы, святыни, маршруты и паломнические поездки по Москве и Московской области.')">
    <title>@yield('title', 'Московский паломник')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/pilgrim.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/pilgrim-site.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pilgrim-account.css') }}" rel="stylesheet">
    @stack('styles')
</head>
@php
    $sitePreferences = auth()->check() ? (auth()->user()->preferences ?: []) : [];
    $themeClass = ($sitePreferences['theme'] ?? 'light') === 'dark' ? 'theme-dark' : '';
    $fontClass = match($sitePreferences['font_size'] ?? 'normal') {
        'large' => 'font-large',
        'extra_large' => 'font-extra_large',
        default => '',
    };
    $unreadNotificationCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp
<body class="{{ $themeClass }} {{ $fontClass }}" data-theme-preference="{{ $sitePreferences['theme'] ?? 'light' }}">
<header class="site-header">
    <nav class="navbar navbar-expand-xl py-3">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <span class="brand-mark"><i class="bi bi-cross"></i></span>
                <span><span class="pm-serif d-block lh-1">Московский паломник</span><small class="d-block text-secondary fw-normal mt-1" style="font-size:.68rem;letter-spacing:.08em;text-transform:uppercase">Путеводитель по святыням</small></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation" aria-controls="siteNavigation" aria-expanded="false" aria-label="Открыть меню"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="siteNavigation">
                <ul class="navbar-nav mx-auto gap-xl-1">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Главная</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}">Карта</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('objects.*') ? 'active' : '' }}" href="{{ route('objects.index') }}">Храмы и святыни</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('routes.*') ? 'active' : '' }}" href="{{ route('routes.index') }}">Маршруты</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}">Календарь</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('community.*') || request()->routeIs('together.*') ? 'active' : '' }}" href="{{ route('community.index') }}">Сообщество</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-light btn-sm" href="{{ route('help') }}" title="Помощь"><i class="bi bi-question-circle"></i></a>
                    @auth
                        <a class="btn btn-light btn-sm position-relative" href="{{ route('notifications.index') }}" title="Уведомления"><i class="bi bi-bell"></i>@if($unreadNotificationCount)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>@endif</a>
                        <div class="dropdown">
                            <button class="btn btn-outline-pm btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" type="button">@if(auth()->user()->avatar_url)<img src="{{ auth()->user()->avatar_url }}" alt="" style="width:24px;height:24px;object-fit:cover;border-radius:50%">@else<i class="bi bi-person-circle"></i>@endif<span class="d-none d-sm-inline">{{ auth()->user()->name }}</span></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                <li><a class="dropdown-item rounded-3" href="{{ route('profile.dashboard') }}"><i class="bi bi-grid me-2"></i>Личный кабинет</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('help') }}"><i class="bi bi-question-circle me-2"></i>Помощь</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('notifications.index') }}"><i class="bi bi-bell me-2"></i>Уведомления @if($unreadNotificationCount)<span class="badge text-bg-danger ms-1">{{ $unreadNotificationCount }}</span>@endif</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('profile.favorites') }}"><i class="bi bi-heart me-2"></i>Избранное</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('profile.bookings') }}"><i class="bi bi-ticket-perforated me-2"></i>Мои билеты</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('route-plans.index') }}"><i class="bi bi-signpost-split me-2"></i>Мои маршруты</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('together.my') }}"><i class="bi bi-people me-2"></i>Мои совместные поездки</a></li>
                                @if(auth()->user()->canManageObjects())<li><a class="dropdown-item rounded-3" href="{{ route('service.dashboard') }}"><i class="bi bi-building-check me-2"></i>Кабинет представителя</a></li><li><a class="dropdown-item rounded-3" href="{{ route('service.tickets.scanner') }}"><i class="bi bi-qr-code-scan me-2"></i>Проверка билетов</a></li>@endif
                                @if(auth()->user()->isAdmin())<li><a class="dropdown-item rounded-3" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Админ-панель</a></li>@endif
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item rounded-3 text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Выйти</button></form></li>
                            </ul>
                        </div>
                    @else
                        <a class="btn btn-outline-pm btn-sm" href="{{ route('login') }}">Войти</a>
                        <a class="btn btn-pm-gold btn-sm px-3" href="{{ route('register') }}">Регистрация</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>

@if(session('success') || session('error') || $errors->any())
<div class="site-alerts">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert" type="button"></button></div>@endif
    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert" type="button"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger alert-dismissible fade show"><strong>Проверьте форму:</strong><ul class="small mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button class="btn-close" data-bs-dismiss="alert" type="button"></button></div>@endif
</div>
@endif

<main>@yield('content')</main>

<footer class="site-footer py-5"><div class="container"><div class="row g-4">
<div class="col-lg-4"><div class="d-flex align-items-center gap-3 mb-3"><span class="brand-mark"><i class="bi bi-cross"></i></span><div><div class="pm-serif fs-5 text-white">Московский паломник</div><div class="small opacity-75">Единая цифровая платформа паломничества</div></div></div><p class="small mb-0">Храмы, монастыри, святыни, события и паломнические маршруты по Москве и Московской области.</p></div>
<div class="col-6 col-lg-2"><div class="text-white fw-semibold mb-3">Разделы</div><div class="d-flex flex-column gap-2 small"><a class="footer-link" href="{{ route('map') }}">Карта</a><a class="footer-link" href="{{ route('objects.index') }}">Объекты</a><a class="footer-link" href="{{ route('routes.index') }}">Маршруты</a><a class="footer-link" href="{{ route('calendar.index') }}">Календарь</a><a class="footer-link" href="{{ route('community.index') }}">Сообщество</a><a class="footer-link" href="{{ route('together.index') }}">Паломничество вместе</a></div></div>
<div class="col-6 col-lg-3"><div class="text-white fw-semibold mb-3">Личный кабинет</div><div class="d-flex flex-column gap-2 small">@auth<a class="footer-link" href="{{ route('profile.dashboard') }}">Профиль</a><a class="footer-link" href="{{ route('notifications.index') }}">Уведомления</a><a class="footer-link" href="{{ route('profile.bookings') }}">Билеты</a><a class="footer-link" href="{{ route('profile.achievements') }}">Достижения</a><a class="footer-link" href="{{ route('route-plans.index') }}">Мои маршруты</a><a class="footer-link" href="{{ route('together.my') }}">Мои группы</a>@else<a class="footer-link" href="{{ route('login') }}">Вход</a><a class="footer-link" href="{{ route('register') }}">Регистрация</a>@endauth</div></div>
<div class="col-lg-3"><div class="text-white fw-semibold mb-3">Работа без сети</div><p class="small mb-3">Карточку выбранного объекта можно сохранить в кэш браузера. Полные офлайн-карты будут реализованы в мобильном приложении.</p><button class="btn btn-sm btn-outline-light" id="installAppButton" type="button" hidden>Установить приложение</button></div>
</div><hr class="border-light border-opacity-10 my-4"><div class="d-flex flex-wrap justify-content-between gap-3 small opacity-75"><span>© {{ date('Y') }} Московский паломник</span><span class="d-flex flex-wrap gap-3"><a class="footer-link" href="{{ route('privacy') }}">Персональные данные</a><a class="footer-link" href="{{ route('terms') }}">Правила сервиса</a><a class="footer-link" href="{{ route('help') }}">Помощь</a></span></div></div></footer>

<nav class="mobile-bottom-nav" aria-label="Мобильная навигация">
    <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house"></i><span>Главная</span></a>
    <a class="{{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}"><i class="bi bi-map"></i><span>Карта</span></a>
    <a class="{{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}"><i class="bi bi-calendar-event"></i><span>Календарь</span></a>
    <a class="{{ request()->routeIs('community.*') || request()->routeIs('together.*') ? 'active' : '' }}" href="{{ route('community.index') }}"><i class="bi bi-people"></i><span>Сообщество</span></a>
    <a class="{{ request()->routeIs('profile.*') || request()->routeIs('route-plans.*') || request()->routeIs('notifications.*') || request()->routeIs('tickets.*') ? 'active' : '' }} position-relative" href="{{ auth()->check() ? route('profile.dashboard') : route('login') }}"><i class="bi bi-person"></i><span>Профиль</span>@if($unreadNotificationCount)<span class="position-absolute top-0 end-0 badge rounded-pill bg-danger">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>@endif</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/together-chat.js') }}"></script>
<script>
(function () {
    if (document.body.dataset.themePreference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) document.body.classList.add('theme-dark');
    if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}'));
    let deferredInstallPrompt = null;
    const installButton = document.getElementById('installAppButton');
    window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); deferredInstallPrompt = event; if (installButton) installButton.hidden = false; });
    installButton?.addEventListener('click', async () => { if (!deferredInstallPrompt) return; deferredInstallPrompt.prompt(); await deferredInstallPrompt.userChoice; deferredInstallPrompt = null; installButton.hidden = true; });
})();
</script>
@stack('scripts')
</body>
</html>
