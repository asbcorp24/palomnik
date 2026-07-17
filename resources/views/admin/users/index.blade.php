@extends('admin.layouts.app')

@section('title', 'Пользователи и личные кабинеты')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title"><i class="bi bi-people me-2"></i>Пользователи и личные кабинеты</h1>
        <div class="page-subtitle">Профили, роли, активность и статистика паломников.</div>
    </div>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.users.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-lg-5">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Имя, email или телефон">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="role">Роль</label>
            <select class="form-select" id="role" name="role">
                <option value="">Все роли</option>
                @foreach($roles as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="form-label" for="active">Состояние</label>
            <select class="form-select" id="active" name="active">
                <option value="">Все</option>
                <option value="1" @selected(($filters['active'] ?? '') === '1')>Активные</option>
                <option value="0" @selected(($filters['active'] ?? '') === '0')>Отключённые</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-2 d-grid">
            <button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button>
        </div>
    </div>
</form>

<div class="card-soft p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
            <tr>
                <th>Пользователь</th>
                <th>Роль</th>
                <th>Посещения</th>
                <th>Бронирования</th>
                <th>Достижения</th>
                <th>Контент</th>
                <th>Состояние</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="small text-secondary">{{ $user->email }}</div>
                        @if($user->phone)<div class="small text-secondary">{{ $user->phone }}</div>@endif
                    </td>
                    <td>{{ $roles[$user->role] ?? $user->role }}</td>
                    <td>{{ $user->visits_count }}</td>
                    <td>{{ $user->bookings_count }}</td>
                    <td>{{ $user->achievements_count }}</td>
                    <td class="small">Отзывы: {{ $user->reviews_count }}<br>Статьи: {{ $user->blog_posts_count }}</td>
                    <td><span class="badge rounded-pill {{ $user->is_active ? 'badge-published' : 'badge-draft' }}">{{ $user->is_active ? 'Активен' : 'Отключён' }}</span></td>
                    <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('admin.users.show', $user) }}"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-5 text-center text-secondary">Пользователей пока нет.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="p-3 border-top">{{ $users->links() }}</div>@endif
</div>
@endsection
