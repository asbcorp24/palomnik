@extends('site.layouts.app')

@section('title', $pilgrimageRoute->title.' — Московский паломник')
@section('meta_description', $pilgrimageRoute->short_description ?: 'Паломнический маршрут '.$pilgrimageRoute->title)

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('routes.index') }}">Маршруты</a></li><li class="breadcrumb-item active">{{ $pilgrimageRoute->title }}</li></ol></nav>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge rounded-pill object-type-badge">{{ $categories[$pilgrimageRoute->category] ?? $pilgrimageRoute->category }}</span>
            <span class="badge rounded-pill text-bg-light">{{ $difficulties[$pilgrimageRoute->difficulty] ?? $pilgrimageRoute->difficulty }}</span>
            @if($pilgrimageRoute->is_group)<span class="badge rounded-pill text-bg-light">Групповой</span>@endif
        </div>
        <h1 class="section-title mb-3">{{ $pilgrimageRoute->title }}</h1>
        @if($pilgrimageRoute->short_description)<p class="section-lead mb-0">{{ $pilgrimageRoute->short_description }}</p>@endif
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                @if($pilgrimageRoute->cover_url)
                    <img class="detail-cover mb-5" src="{{ $pilgrimageRoute->cover_url }}" alt="{{ $pilgrimageRoute->title }}">
                @else
                    <div class="object-placeholder detail-placeholder mb-5"><i class="bi bi-signpost-split"></i></div>
                @endif

                @if($pilgrimageRoute->description)
                    <section class="mb-5">
                        <div class="section-kicker mb-2">О маршруте</div>
                        <h2 class="h2 mb-4">Описание</h2>
                        <div class="text-secondary lh-lg">{!! nl2br(e($pilgrimageRoute->description)) !!}</div>
                    </section>
                @endif

                @if($pilgrimageRoute->program)
                    <section class="mb-5">
                        <div class="section-kicker mb-2">Поэтапно</div>
                        <h2 class="h2 mb-4">Программа</h2>
                        <div class="filter-card lh-lg">{!! nl2br(e($pilgrimageRoute->program)) !!}</div>
                    </section>
                @endif

                <section>
                    <div class="section-kicker mb-2">Точки пути</div>
                    <h2 class="h2 mb-4">Объекты маршрута</h2>
                    <div class="d-grid gap-3">
                        @forelse($pilgrimageRoute->objects as $index => $object)
                            <article class="info-card">
                                <div class="d-flex gap-3 align-items-start">
                                    <div class="step-number flex-shrink-0">{{ $index + 1 }}</div>
                                    <div class="flex-grow-1">
                                        <div class="small text-secondary mb-1">{{ optional($object->objectType)->name }}</div>
                                        <h3 class="h5 mb-2"><a class="text-decoration-none" href="{{ route('objects.show', $object) }}">{{ $object->name }}</a></h3>
                                        <div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $object->address }}</div>
                                        @if($object->pivot->stay_minutes)<div class="small"><i class="bi bi-clock me-1"></i>Остановка: {{ $object->pivot->stay_minutes }} мин.</div>@endif
                                        @if($object->pivot->note)<div class="small text-secondary mt-2">{{ $object->pivot->note }}</div>@endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="filter-card text-secondary">Точки маршрута ещё не добавлены.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="col-lg-4">
                <div class="position-sticky d-grid gap-3" style="top:105px">
                    <div class="info-card">
                        <h2 class="h5 mb-4">Параметры</h2>
                        <div class="d-grid gap-3 small">
                            <div class="d-flex justify-content-between"><span class="text-secondary">Продолжительность</span><strong>{{ $pilgrimageRoute->duration_days }} дн.</strong></div>
                            @if($pilgrimageRoute->duration_minutes)<div class="d-flex justify-content-between"><span class="text-secondary">Расчётное время</span><strong>{{ $pilgrimageRoute->duration_minutes }} мин.</strong></div>@endif
                            <div class="d-flex justify-content-between"><span class="text-secondary">Сложность</span><strong>{{ $difficulties[$pilgrimageRoute->difficulty] ?? $pilgrimageRoute->difficulty }}</strong></div>
                            <div class="d-flex justify-content-between"><span class="text-secondary">Количество точек</span><strong>{{ $pilgrimageRoute->objects->count() }}</strong></div>
                            <div class="d-flex justify-content-between"><span class="text-secondary">Базовая стоимость</span><strong>{{ $pilgrimageRoute->base_price !== null ? number_format((float)$pilgrimageRoute->base_price, 0, ',', ' ').' ₽' : 'Бесплатно / уточняется' }}</strong></div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h2 class="h5 mb-3">Ближайшие поездки</h2>
                        @forelse($pilgrimageRoute->trips as $trip)
                            <div class="py-3 border-bottom">
                                <div class="fw-semibold mb-1">{{ $trip->starts_at->format('d.m.Y H:i') }}</div>
                                <div class="small text-secondary mb-2">{{ $trip->meeting_point ?: 'Место сбора уточняется' }}</div>
                                <div class="d-flex justify-content-between small"><span>{{ $trip->status === 'open' ? 'Открыта запись' : 'Запланирована' }}</span><strong>{{ $trip->price !== null ? number_format((float)$trip->price, 0, ',', ' ').' ₽' : 'Цена уточняется' }}</strong></div>
                            </div>
                        @empty
                            <p class="small text-secondary mb-0">Даты организованных поездок пока не опубликованы. Маршрут можно пройти самостоятельно.</p>
                        @endforelse
                    </div>

                    @if($pilgrimageRoute->trips->where('status', 'open')->isNotEmpty())
                        <button class="btn btn-pm-gold py-3" type="button" disabled><i class="bi bi-ticket-perforated me-2"></i>Онлайн-запись подключается</button>
                    @endif
                    <a class="btn btn-outline-pm py-3" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Открыть карту</a>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
