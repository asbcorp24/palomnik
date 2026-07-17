<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Московский паломник — храмы, святыни, маршруты и паломнические поездки по Москве и Московской области.')">
    <title>@yield('title', 'Московский паломник')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/pilgrim-site.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
<header class="site-header">
    <nav class="navbar navbar-expand-lg py-3">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <span class="brand-mark"><i class="bi bi-cross"></i></span>
                <span>
                    <span class="pm-serif d-block lh-1">Московский паломник</span>
                    <small class="d-block text-secondary fw-normal mt-1" style="font-size:.68rem;letter-spacing:.08em;text-transform:uppercase">Путеводитель по святыням</small>
                </span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation" aria-controls="siteNavigation" aria-expanded="false" aria-label="Открыть меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNavigation">
                <ul class="navbar-nav mx-auto gap-lg-2">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Главная</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}">Карта</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('objects.*') ? 'active' : '' }}" href="{{ route('objects.index') }}">Храмы и святыни</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('routes.index') ? 'active' : '' }}" href="{{ route('routes.index') }}">Маршруты</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a class="btn btn-outline-pm btn-sm" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Панель</a>
                        @endif
                    @else
                        <a class="btn btn-outline-pm btn-sm" href="{{ route('login') }}">Войти</a>
                    @endauth
                    <a class="btn btn-pm-gold btn-sm px-3" href="{{ route('objects.index') }}">Найти святыню</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="brand-mark"><i class="bi bi-cross"></i></span>
                    <div>
                        <div class="pm-serif fs-5 text-white">Московский паломник</div>
                        <div class="small opacity-75">Единая цифровая платформа паломничества</div>
                    </div>
                </div>
                <p class="small mb-0" style="max-width:520px">Храмы, монастыри, святыни и готовые маршруты по Москве и Московской области — в одном понятном пространстве.</p>
            </div>
            <div class="col-6 col-lg-2">
                <div class="text-white fw-semibold mb-3">Разделы</div>
                <div class="d-flex flex-column gap-2 small">
                    <a class="footer-link" href="{{ route('map') }}">Карта</a>
                    <a class="footer-link" href="{{ route('objects.index') }}">Объекты</a>
                    <a class="footer-link" href="{{ route('routes.index') }}">Маршруты</a>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="text-white fw-semibold mb-3">Платформа</div>
                <div class="d-flex flex-column gap-2 small">
                    <a class="footer-link" href="{{ route('login') }}">Вход</a>
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a class="footer-link" href="{{ route('admin.dashboard') }}">Администрирование</a>
                        @endif
                    @endauth
                </div>
            </div>
            <div class="col-lg-3">
                <div class="text-white fw-semibold mb-3">Проект развивается</div>
                <p class="small mb-0">Сейчас наполняется единый реестр объектов. Следующий этап — маршруты, бронирования и личный кабинет.</p>
            </div>
        </div>
        <hr class="border-light border-opacity-10 my-4">
        <div class="small opacity-75">© {{ date('Y') }} Московский паломник</div>
    </div>
</footer>

<nav class="mobile-bottom-nav" aria-label="Мобильная навигация">
    <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><i class="bi bi-house"></i><span>Главная</span></a>
    <a class="{{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}"><i class="bi bi-map"></i><span>Карта</span></a>
    <a class="{{ request()->routeIs('objects.*') ? 'active' : '' }}" href="{{ route('objects.index') }}"><i class="bi bi-geo-alt"></i><span>Святыни</span></a>
    <a class="{{ request()->routeIs('routes.index') ? 'active' : '' }}" href="{{ route('routes.index') }}"><i class="bi bi-signpost-split"></i><span>Маршруты</span></a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
