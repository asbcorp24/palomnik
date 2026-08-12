@extends('site.layouts.app')

@section('title', 'Паломнические маршруты — Московский паломник')

@push('styles')
<style>
    .trip-calendar-card { transition: transform .18s ease, box-shadow .18s ease; }
    .trip-calendar-card:hover { transform: translateY(-2px); }
    .trip-calendar-date { width:76px; min-width:76px; border-radius:18px; background:var(--pm-cream); padding:.75rem .45rem; text-align:center; }
    .trip-calendar-date .day { font-family:Georgia,serif; font-size:2rem; line-height:1; color:var(--pm-green); }
    .trip-calendar-date .month { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--pm-muted); }
    .trip-calendar-date .weekday { font-size:.72rem; color:var(--pm-muted); }
    @media(max-width:575.98px) { .trip-calendar-date { width:66px; min-width:66px; } }
</style>
@endpush

@section('content')
@php
    $tripMonthNames = [1=>'янв',2=>'фев',3=>'мар',4=>'апр',5=>'май',6=>'июн',7=>'июл',8=>'авг',9=>'сен',10=>'окт',11=>'ноя',12=>'дек'];
    $tripWeekdays = [1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];
@endphp
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item active">Маршруты</li></ol></nav>
        <div class="section-kicker mb-2">Планирование паломничества</div>
        <h1 class="section-title mb-3">Паломнические маршруты</h1>
        <p class="section-lead mb-0">Однодневные, многодневные, тематические, семейные и молодёжные программы.</p>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="card-pm p-4 p-lg-5 mb-5 overflow-hidden position-relative">
            <div class="row align-items-center g-4 position-relative" style="z-index:1">
                <div class="col-lg-8">
                    <div class="section-kicker mb-2">Персональный подбор</div>
                    <h2 class="h2 mb-3">Составьте маршрут дня</h2>
                    <p class="text-secondary mb-0">Укажите местоположение, свободное время, способ передвижения и интересующую тему. Система подберёт храмы и монастыри, проверит расписания и рассчитает путь.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a class="btn btn-pm-gold btn-lg px-4" href="{{ route('day-route.index') }}"><i class="bi bi-stars me-2"></i>Составить маршрут</a>
                </div>
            </div>
        </div>

        <form class="filter-card mb-5" method="GET" action="{{ route('routes.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="q">Поиск маршрута</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название или описание">
                </div>
                <div class="col-md-5 col-lg-3">
                    <label class="form-label" for="category">Категория</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Все категории</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="difficulty">Сложность</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                        <option value="">Любая</option>
                        @foreach($difficulties as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['difficulty'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button class="btn btn-pm-gold" type="submit"><i class="bi bi-funnel me-1"></i>Найти</button>
                </div>
            </div>
        </form>

        <div class="row g-4">
            @forelse($routes as $pilgrimageRoute)
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm">
                        @if($pilgrimageRoute->cover_url)
                            <img class="object-cover" src="{{ $pilgrimageRoute->cover_url }}" alt="{{ $pilgrimageRoute->title }}">
                        @else
                            <div class="object-placeholder"><i class="bi bi-signpost-split"></i></div>
                        @endif
                        <div class="p-4">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge rounded-pill object-type-badge">{{ $categories[$pilgrimageRoute->category] ?? $pilgrimageRoute->category }}</span>
                                <span class="badge rounded-pill text-bg-light">{{ $difficulties[$pilgrimageRoute->difficulty] ?? $pilgrimageRoute->difficulty }}</span>
                            </div>
                            <h2 class="object-title mb-3">{{ $pilgrimageRoute->title }}</h2>
                            <div class="object-meta d-flex flex-wrap gap-3 mb-3">
                                <span><i class="bi bi-clock me-1"></i>{{ $pilgrimageRoute->duration_days }} дн.</span>
                                <span><i class="bi bi-geo-alt me-1"></i>{{ $pilgrimageRoute->objects_count }} точек</span>
                                @if($pilgrimageRoute->base_price !== null)<span><i class="bi bi-wallet2 me-1"></i>от {{ number_format((float)$pilgrimageRoute->base_price, 0, ',', ' ') }} ₽</span>@endif
                            </div>
                            @if($pilgrimageRoute->short_description)<p class="text-secondary small mb-4">{{ \Illuminate\Support\Str::limit($pilgrimageRoute->short_description, 160) }}</p>@endif
                            <a class="btn btn-pm-gold w-100" href="{{ route('routes.show', $pilgrimageRoute) }}">Открыть маршрут</a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="filter-card p-5 text-center">
                        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-signpost-split"></i></div>
                        <h2 class="h4 mb-3">Опубликованных маршрутов пока нет</h2>
                        <p class="text-secondary mb-4">Создайте маршрут в административной панели, добавьте точки и включите публикацию.</p>
                        @auth
                            @if(auth()->user()->isAdmin())<a class="btn btn-pm-green" href="{{ route('admin.modules.create', 'routes') }}">Создать маршрут</a>@endif
                        @endauth
                    </div>
                </div>
            @endforelse
        </div>

        @if($routes->hasPages())<div class="mt-5">{{ $routes->links() }}</div>@endif

        <section class="mt-5 pt-5 border-top" id="upcoming-trips-calendar">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <div class="section-kicker mb-2">Организованные паломничества</div>
                    <h2 class="h2 mb-2">Календарь ближайших поездок</h2>
                    <p class="text-secondary mb-0">Ближайшие даты поездок по опубликованным маршрутам.</p>
                </div>
                <span class="badge rounded-pill text-bg-light px-3 py-2">{{ $upcomingTrips->count() }} ближайших</span>
            </div>

            <div class="row g-3">
                @forelse($upcomingTrips as $trip)
                    @php
                        $remaining = $trip->capacity === null ? null : max(0, $trip->capacity - $trip->booked_count);
                        $tripTitle = $trip->title ?: optional($trip->pilgrimageRoute)->title;
                    @endphp
                    <div class="col-lg-6">
                        <article class="info-card trip-calendar-card h-100">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="trip-calendar-date flex-shrink-0">
                                    <div class="month">{{ $tripMonthNames[$trip->starts_at->month] }}</div>
                                    <div class="day">{{ $trip->starts_at->format('d') }}</div>
                                    <div class="weekday">{{ $tripWeekdays[$trip->starts_at->dayOfWeekIso] }}</div>
                                </div>

                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                        <span class="badge rounded-pill {{ $trip->status === 'open' ? 'badge-published' : 'text-bg-light' }}">
                                            {{ $trip->status === 'open' ? 'Открыта запись' : 'Запланирована' }}
                                        </span>
                                        <span class="small text-secondary"><i class="bi bi-clock me-1"></i>{{ $trip->starts_at->format('H:i') }}</span>
                                    </div>

                                    <h3 class="h5 mb-2">{{ $tripTitle ?: 'Паломническая поездка' }}</h3>

                                    @if($trip->pilgrimageRoute)
                                        <div class="small text-secondary mb-2">
                                            <i class="bi bi-signpost-split me-1"></i>{{ $trip->pilgrimageRoute->title }}
                                        </div>
                                    @endif

                                    <div class="small text-secondary mb-2">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $trip->meeting_point ?: 'Место сбора уточняется' }}
                                    </div>

                                    <div class="d-flex flex-wrap gap-3 small mb-3">
                                        <span><i class="bi bi-wallet2 me-1"></i>{{ $trip->price !== null ? number_format((float)$trip->price, 0, ',', ' ').' ₽' : 'Цена уточняется' }}</span>
                                        @if($remaining !== null)
                                            <span><i class="bi bi-people me-1"></i>Свободно мест: {{ $remaining }}</span>
                                        @endif
                                    </div>

                                    @if($trip->pilgrimageRoute)
                                        <a class="btn btn-outline-pm btn-sm" href="{{ route('routes.show', $trip->pilgrimageRoute) }}">
                                            Подробнее о поездке <i class="bi bi-chevron-right ms-1"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="filter-card text-center py-5">
                            <i class="bi bi-calendar2-week display-5 text-secondary"></i>
                            <h3 class="h4 mt-3 mb-2">Ближайших поездок пока нет</h3>
                            <p class="text-secondary mb-0">Когда для маршрутов будут опубликованы новые даты, они появятся здесь автоматически.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</section>
@endsection
