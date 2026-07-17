@extends('site.layouts.app')

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="section-kicker mb-2">Личный кабинет</div>
        <h1 class="section-title mb-2">@yield('profile_title', 'Профиль паломника')</h1>
        <p class="section-lead mb-0">@yield('profile_subtitle', 'Ваши маршруты, поездки, посещения и достижения.')</p>
    </div>
</section>

<section class="profile-shell">
    <div class="container">
        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="profile-sidebar">
                    <div class="d-flex align-items-center gap-3">
                        <div class="profile-avatar">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}">
                            @else
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="pm-serif fs-5 text-truncate">{{ auth()->user()->name }}</div>
                            <div class="small text-secondary text-truncate">{{ auth()->user()->email }}</div>
                        </div>
                    </div>

                    <nav class="profile-nav">
                        <a class="{{ request()->routeIs('profile.dashboard') ? 'active' : '' }}" href="{{ route('profile.dashboard') }}"><i class="bi bi-grid"></i>Обзор</a>
                        <a class="{{ request()->routeIs('profile.favorites') ? 'active' : '' }}" href="{{ route('profile.favorites') }}"><i class="bi bi-heart"></i>Избранное</a>
                        <a class="{{ request()->routeIs('profile.bookings') ? 'active' : '' }}" href="{{ route('profile.bookings') }}"><i class="bi bi-ticket-perforated"></i>Бронирования</a>
                        <a class="{{ request()->routeIs('profile.achievements') ? 'active' : '' }}" href="{{ route('profile.achievements') }}"><i class="bi bi-trophy"></i>Достижения</a>
                        <a class="{{ request()->routeIs('route-plans.*') ? 'active' : '' }}" href="{{ route('route-plans.index') }}"><i class="bi bi-signpost-split"></i>Мои маршруты</a>
                        <a class="{{ request()->routeIs('profile.activity') ? 'active' : '' }}" href="{{ route('profile.activity') }}"><i class="bi bi-activity"></i>Моя активность</a>
                        <a class="{{ request()->routeIs('profile.settings') ? 'active' : '' }}" href="{{ route('profile.settings') }}"><i class="bi bi-sliders"></i>Настройки</a>
                    </nav>

                    @if(auth()->user()->isAdmin())
                        <hr>
                        <a class="btn btn-outline-pm w-100" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Админ-панель</a>
                    @endif
                </div>
            </aside>
            <div class="col-lg-9">
                @yield('profile_content')
            </div>
        </div>
    </div>
</section>
@endsection
