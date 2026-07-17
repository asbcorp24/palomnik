@extends('site.profile.layout')

@section('title', 'Личный кабинет — Московский паломник')
@section('profile_title', 'Здравствуйте, '.$user->name)
@section('profile_subtitle', 'Здесь собрана ваша история паломничества и ближайшие планы.')

@section('profile_content')
<div class="row g-3 mb-4">
    @foreach([
        [$user->visits_count, 'Посещений', 'bi-geo-fill'],
        [$user->bookings_count, 'Бронирований', 'bi-ticket-perforated'],
        [$user->achievements_count, 'Достижений', 'bi-trophy'],
        [$user->favorite_lists_count, 'Списков', 'bi-heart'],
    ] as $stat)
        <div class="col-6 col-xl-3">
            <div class="profile-stat">
                <i class="bi {{ $stat[2] }} fs-4" style="color:var(--pm-gold-dark)"></i>
                <div class="profile-stat-value mt-3">{{ $stat[0] }}</div>
                <div class="small text-secondary">{{ $stat[1] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="profile-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><div class="section-kicker mb-1">Поездки</div><h2 class="h4 mb-0">Последние бронирования</h2></div>
                <a class="btn btn-sm btn-outline-pm" href="{{ route('profile.bookings') }}">Все</a>
            </div>
            @forelse($latestBookings as $booking)
                @php
                    $statusClass = match($booking->status) {
                        'confirmed' => 'status-confirmed',
                        'cancelled', 'refunded' => 'status-cancelled',
                        'completed' => 'status-published',
                        default => 'status-pending',
                    };
                @endphp
                <div class="py-3 border-bottom">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">{{ optional(optional($booking->trip)->pilgrimageRoute)->title ?: 'Паломническая поездка' }}</div>
                            <div class="small text-secondary mt-1">{{ optional(optional($booking->trip)->starts_at)->format('d.m.Y H:i') ?: 'Дата уточняется' }}</div>
                        </div>
                        <span class="status-badge {{ $statusClass }}">{{ $booking->status }}</span>
                    </div>
                </div>
            @empty
                <div class="empty-state"><i class="bi bi-ticket-perforated display-5 d-block mb-3"></i>Бронирований пока нет.<div class="mt-3"><a class="btn btn-pm-gold" href="{{ route('routes.index') }}">Выбрать маршрут</a></div></div>
            @endforelse
        </div>
    </div>

    <div class="col-xl-5">
        <div class="profile-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><div class="section-kicker mb-1">История</div><h2 class="h4 mb-0">Последние посещения</h2></div>
                <a class="btn btn-sm btn-outline-pm" href="{{ route('profile.activity') }}">Все</a>
            </div>
            @forelse($latestVisits as $visit)
                <div class="d-flex gap-3 py-3 border-bottom">
                    <span class="info-icon flex-shrink-0"><i class="bi bi-geo-alt"></i></span>
                    <div>
                        <div class="fw-semibold">{{ optional($visit->pilgrimageObject)->name ?: 'Объект удалён' }}</div>
                        <div class="small text-secondary mt-1">{{ $visit->visited_at->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="empty-state py-4">Посещений пока нет.</div>
            @endforelse
        </div>
    </div>

    <div class="col-12">
        <div class="profile-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div><div class="section-kicker mb-1">Следующий шаг</div><h2 class="h4 mb-0">Продолжите путь</h2></div>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><a class="category-card h-100" href="{{ route('map') }}"><span class="category-icon"><i class="bi bi-map"></i></span><span><strong class="d-block">Открыть карту</strong><small class="text-secondary">Найти святыни рядом</small></span></a></div>
                <div class="col-md-4"><a class="category-card h-100" href="{{ route('route-plans.create') }}"><span class="category-icon"><i class="bi bi-signpost-split"></i></span><span><strong class="d-block">Собрать маршрут</strong><small class="text-secondary">Выбрать свои точки</small></span></a></div>
                <div class="col-md-4"><a class="category-card h-100" href="{{ route('community.index') }}"><span class="category-icon"><i class="bi bi-people"></i></span><span><strong class="d-block">Сообщество</strong><small class="text-secondary">Отзывы и заметки</small></span></a></div>
            </div>
        </div>
    </div>
</div>
@endsection
