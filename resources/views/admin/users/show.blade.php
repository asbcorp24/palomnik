@extends('admin.layouts.app')

@section('title', $user->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><a class="small text-decoration-none text-secondary" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-left me-1"></i>Пользователи</a><h1 class="page-title mt-2">{{ $user->name }}</h1><div class="page-subtitle">Личный кабинет, права, безопасность и статистика пользователя.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-green" href="{{ route('admin.representatives.index', ['q' => $user->email]) }}"><i class="bi bi-person-badge me-1"></i>Назначения объектов</a>@if($user->canManageObjects())<a class="btn btn-outline-green" href="{{ route('service.dashboard') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Кабинет представителя</a>@endif</div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">@csrf @method('PUT')
            <div class="card-soft p-4">
                <h2 class="h5 mb-4">Профиль и доступ</h2>
                <div class="mb-3"><label class="form-label required" for="name">Имя</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
                <div class="mb-3"><label class="form-label required" for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                <div class="mb-3"><label class="form-label" for="phone">Телефон</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                <div class="mb-3"><label class="form-label required" for="role">Роль</label><select class="form-select" id="role" name="role" required>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="form-check form-switch mb-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $user->is_active))><label class="form-check-label" for="is_active">Учётная запись активна</label></div>
                <div class="form-check form-switch mb-4"><input type="hidden" name="is_verified_organizer" value="0"><input class="form-check-input" id="is_verified_organizer" type="checkbox" name="is_verified_organizer" value="1" @checked((bool)old('is_verified_organizer', $user->is_verified_organizer))><label class="form-check-label" for="is_verified_organizer">Проверенный организатор</label><div class="form-text">Отметка показывается в совместных паломничествах.</div></div>
                <button class="btn btn-gold w-100" type="submit">Сохранить профиль</button>
            </div>
        </form>

        <div class="card-soft p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Закреплённые объекты</h2><span class="badge rounded-pill text-bg-light">{{ $user->object_representatives_count }}</span></div>
            <div class="d-grid gap-2">@forelse($user->objectRepresentatives as $assignment)<div class="border rounded-3 p-3"><div class="fw-semibold">{{ optional($assignment->pilgrimageObject)->name ?: 'Объект удалён' }}</div><div class="small text-secondary mt-1">{{ $assignment->role }} · {{ $assignment->status }}</div></div>@empty<div class="small text-secondary">Назначений пока нет.</div>@endforelse</div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="row g-3 mb-4">
            @foreach([
                ['Посещения', $user->visits_count, 'bi-geo-fill'],
                ['Бронирования', $user->bookings_count, 'bi-ticket-perforated'],
                ['Достижения', $user->achievements_count, 'bi-trophy'],
                ['Отзывы', $user->reviews_count, 'bi-chat-square-text'],
                ['Статьи', $user->blog_posts_count, 'bi-journal-richtext'],
                ['Медиа', $user->media_count, 'bi-camera'],
                ['Списки избранного', $user->favorite_lists_count, 'bi-heart'],
                ['Полученные жалобы', $user->received_reports_count, 'bi-shield-exclamation'],
            ] as $card)
                <div class="col-6 col-lg-3"><div class="card-soft stat-card"><div class="stat-icon"><i class="bi {{ $card[2] }}"></i></div><div class="stat-number">{{ $card[1] }}</div><div class="stat-label">{{ $card[0] }}</div></div></div>
            @endforeach
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-4">Последние посещения</h2>
            @forelse($user->visits as $visit)<div class="d-flex justify-content-between gap-3 py-3 border-bottom"><div><div class="fw-semibold">{{ optional($visit->pilgrimageObject)->name ?: 'Объект удалён' }}</div><div class="small text-secondary">{{ $visit->verification_method }} · {{ $visit->status }}</div></div><div class="small text-secondary text-nowrap">{{ optional($visit->visited_at)->format('d.m.Y H:i') }}</div></div>@empty<div class="text-secondary">Посещений пока нет.</div>@endforelse
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-4">Последние бронирования</h2>
            @forelse($user->bookings as $booking)<div class="d-flex justify-content-between gap-3 py-3 border-bottom"><div><div class="fw-semibold">{{ optional(optional($booking->trip)->pilgrimageRoute)->title ?: 'Маршрут удалён' }}</div><div class="small text-secondary">{{ $booking->status }} · {{ $booking->payment_status }}</div></div><div class="small text-secondary text-nowrap">{{ $booking->created_at->format('d.m.Y') }}</div></div>@empty<div class="text-secondary">Бронирований пока нет.</div>@endforelse
        </div>

        <div class="card-soft p-4"><h2 class="h5 mb-4">Полученные достижения</h2><div class="row g-3">@forelse($user->achievements as $achievement)<div class="col-md-6"><div class="info-card h-100 d-flex gap-3"><div class="stat-icon"><i class="bi {{ $achievement->icon ?: 'bi-trophy' }}"></i></div><div><div class="fw-semibold">{{ $achievement->title }}</div><div class="small text-secondary">{{ $achievement->points }} баллов</div></div></div></div>@empty<div class="col-12 text-secondary">Достижений пока нет.</div>@endforelse</div></div>
    </div>
</div>
@endsection
