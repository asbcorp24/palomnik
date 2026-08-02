@extends('admin.layouts.app')

@section('title', 'Обзор')

@section('content')
@php
    $user = auth()->user();
    $roleLabel = \App\Models\User::roleLabels()[$user->role] ?? $user->role;
    $roleDescription = \App\Models\User::roleDescriptions()[$user->role] ?? '';
    $cards = [];
    $modules = [];

    if ($user->hasPermission(\App\Models\User::PERMISSION_CONTENT_MANAGE)) {
        $cards = array_merge($cards, [
            ['value' => $stats['objects'], 'label' => 'Всего объектов', 'icon' => 'bi-geo-alt'],
            ['value' => $stats['published'], 'label' => 'Опубликовано', 'icon' => 'bi-eye'],
            ['value' => $stats['sanctities'], 'label' => 'Святынь', 'icon' => 'bi-star'],
            ['value' => $stats['media'], 'label' => 'Материалов', 'icon' => 'bi-images'],
        ]);
        $modules = array_merge($modules, [
            ['title' => 'Каталог объектов', 'value' => $stats['objects'], 'icon' => 'bi-geo-alt', 'route' => route('admin.objects.index')],
            ['title' => 'Календарь событий', 'value' => 'Открыть', 'icon' => 'bi-calendar-event', 'route' => route('admin.calendar.index')],
            ['title' => 'Справочник святынь', 'value' => $stats['sanctities'], 'icon' => 'bi-star', 'route' => route('admin.directories.index', 'sanctities')],
        ]);
    }

    if ($user->canManageModule('routes')) {
        $cards[] = ['value' => $moduleStats['routes'], 'label' => 'Маршрутов', 'icon' => 'bi-signpost-split'];
        $modules[] = ['title' => 'Маршруты', 'value' => $moduleStats['routes'], 'icon' => 'bi-signpost-split', 'route' => route('admin.modules.index', 'routes')];
    }

    if ($user->canManageModule('trips')) {
        $cards[] = ['value' => $moduleStats['trips'], 'label' => 'Поездок', 'icon' => 'bi-bus-front'];
        $modules[] = ['title' => 'Расписание поездок', 'value' => $moduleStats['trips'], 'icon' => 'bi-bus-front', 'route' => route('admin.modules.index', 'trips')];
    }

    if ($user->hasPermission(\App\Models\User::PERMISSION_BOOKINGS_MANAGE)) {
        $cards[] = ['value' => $moduleStats['bookings'], 'label' => 'Бронирований', 'icon' => 'bi-ticket-perforated'];
        $modules[] = ['title' => 'CRM заявок', 'value' => $moduleStats['bookings'], 'icon' => 'bi-headset', 'route' => route('admin.crm.index')];
        $modules[] = ['title' => 'Сканер QR-билетов', 'value' => 'Открыть', 'icon' => 'bi-qr-code-scan', 'route' => route('service.tickets.scanner')];
    }

    if ($user->hasPermission(\App\Models\User::PERMISSION_MODERATION_MANAGE)) {
        $cards = array_merge($cards, [
            ['value' => $moduleStats['visits_pending'], 'label' => 'Посещений на проверке', 'icon' => 'bi-geo-fill'],
            ['value' => $moduleStats['reviews_pending'], 'label' => 'Отзывов на проверке', 'icon' => 'bi-chat-square-text'],
            ['value' => $moduleStats['posts_pending'], 'label' => 'Публикаций на проверке', 'icon' => 'bi-journal-richtext'],
            ['value' => $moduleStats['media_pending'], 'label' => 'Фото на проверке', 'icon' => 'bi-camera'],
        ]);
        $modules = array_merge($modules, [
            ['title' => 'Изменения от храмов', 'value' => 'Очередь', 'icon' => 'bi-building-check', 'route' => route('admin.service-review.index')],
            ['title' => 'Отзывы', 'value' => $moduleStats['reviews_pending'], 'icon' => 'bi-chat-square-text', 'route' => route('admin.moderation.index', 'reviews')],
            ['title' => 'Паломнические фото', 'value' => $moduleStats['media_pending'], 'icon' => 'bi-camera', 'route' => route('admin.moderation.index', 'media')],
            ['title' => 'Безопасность и жалобы', 'value' => 'Открыть', 'icon' => 'bi-shield-exclamation', 'route' => route('admin.safety.index')],
        ]);
    }

    if ($user->hasPermission(\App\Models\User::PERMISSION_USERS_VIEW)) {
        $cards[] = ['value' => $moduleStats['users'], 'label' => 'Пользователей', 'icon' => 'bi-people'];
        $modules[] = ['title' => 'Пользователи', 'value' => $moduleStats['users'], 'icon' => 'bi-people', 'route' => route('admin.users.index')];
    }
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="section-kicker text-uppercase small fw-semibold mb-2" style="color:var(--pilgrim-gold)">{{ $roleLabel }}</div>
        <h1 class="page-title">Рабочая панель</h1>
        <div class="page-subtitle">{{ $roleDescription }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-green" href="{{ route('home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Открыть сайт</a>
        @if($user->hasPermission(\App\Models\User::PERMISSION_CONTENT_MANAGE))
            <a class="btn btn-gold" href="{{ route('admin.objects.create') }}"><i class="bi bi-plus-lg me-1"></i>Добавить объект</a>
        @elseif($user->hasPermission(\App\Models\User::PERMISSION_BOOKINGS_MANAGE))
            <a class="btn btn-gold" href="{{ route('admin.crm.index') }}"><i class="bi bi-headset me-1"></i>Открыть CRM</a>
        @elseif($user->hasPermission(\App\Models\User::PERMISSION_MODERATION_MANAGE))
            <a class="btn btn-gold" href="{{ route('admin.moderation.index', 'reviews') }}"><i class="bi bi-check2-square me-1"></i>Открыть модерацию</a>
        @endif
    </div>
</div>

@if($cards)
<div class="row g-3 mb-5">
    @foreach($cards as $card)
        <div class="col-6 col-lg-4 col-xl-3">
            <div class="card-soft stat-card">
                <div class="stat-icon"><i class="bi {{ $card['icon'] }}"></i></div>
                <div class="stat-number">{{ $card['value'] }}</div>
                <div class="stat-label">{{ $card['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>
@endif

<div class="d-flex justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h2 class="h4 mb-1">Доступные разделы</h2>
        <div class="small text-secondary">Набор разделов сформирован согласно назначенной роли.</div>
    </div>
</div>

<div class="row g-3 mb-5">
    @forelse($modules as $module)
        <div class="col-md-6 col-xl-4">
            <a class="card-soft p-4 d-flex align-items-center gap-3 text-decoration-none h-100" href="{{ $module['route'] }}">
                <div class="stat-icon flex-shrink-0"><i class="bi {{ $module['icon'] }}"></i></div>
                <div class="flex-grow-1"><div class="small text-secondary">{{ $module['title'] }}</div><div class="fs-5 fw-bold text-dark">{{ $module['value'] }}</div></div>
                <i class="bi bi-chevron-right text-secondary"></i>
            </a>
        </div>
    @empty
        <div class="col-12"><div class="card-soft p-5 text-center text-secondary">Для этой роли административные разделы не назначены.</div></div>
    @endforelse
</div>

@if($user->hasPermission(\App\Models\User::PERMISSION_CONTENT_MANAGE))
<div class="card-soft p-0 overflow-hidden">
    <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
        <div><h3 class="h5 mb-1">Последние изменения</h3><div class="small text-secondary">Недавно обновлённые храмы и паломнические объекты</div></div>
        <a class="btn btn-sm btn-outline-green" href="{{ route('admin.objects.index') }}">Весь каталог</a>
    </div>
    @if($recentObjects->isEmpty())
        <div class="p-5 text-center text-secondary"><i class="bi bi-geo-alt fs-1 d-block mb-2"></i>Каталог пока пуст.</div>
    @else
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Объект</th><th>Тип</th><th>Викариатство</th><th>Статус</th><th>Обновлён</th><th></th></tr></thead>
                <tbody>
                @foreach($recentObjects as $object)
                    <tr>
                        <td><div class="fw-semibold">{{ $object->name }}</div><div class="small text-secondary text-truncate" style="max-width:360px">{{ $object->address }}</div></td>
                        <td>{{ optional($object->objectType)->name ?? '—' }}</td>
                        <td>{{ optional($object->vicariate)->name ?? '—' }}</td>
                        <td><span class="badge rounded-pill {{ $object->is_published ? 'badge-published' : 'badge-draft' }}">{{ $object->is_published ? 'Опубликован' : 'Черновик' }}</span></td>
                        <td class="small text-secondary">{{ optional($object->updated_at)->format('d.m.Y H:i') }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif
@endsection
