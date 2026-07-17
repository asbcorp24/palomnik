@extends('admin.layouts.app')

@section('title', 'Представители храмов')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="page-title"><i class="bi bi-person-badge me-2"></i>Представители храмов</h1><div class="page-subtitle">Назначение редакторов и управляющих для карточек паломнических объектов.</div></div>
    <a class="btn btn-outline-green" href="{{ route('service.dashboard') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Открыть кабинет</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <form class="card-soft p-4" method="POST" action="{{ route('admin.representatives.store') }}">
            @csrf
            <h2 class="h5 mb-4">Назначить представителя</h2>
            <div class="mb-3"><label class="form-label required" for="user_id">Пользователь</label><select class="form-select" id="user_id" name="user_id" required><option value="">Выберите пользователя</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select><div class="form-text">В списке показываются пользователи с ролями редактора объектов или паломнической службы.</div></div>
            <div class="mb-3"><label class="form-label required" for="pilgrimage_object_id">Объект</label><select class="form-select" id="pilgrimage_object_id" name="pilgrimage_object_id" required><option value="">Выберите объект</option>@foreach($objects as $object)<option value="{{ $object->id }}">{{ $object->name }}</option>@endforeach</select></div>
            <div class="row g-3"><div class="col-md-6"><label class="form-label" for="role">Полномочия</label><select class="form-select" id="role" name="role"><option value="editor">Редактор</option><option value="manager">Управляющий</option></select></div><div class="col-md-6"><label class="form-label" for="status">Статус</label><select class="form-select" id="status" name="status"><option value="approved">Подтверждено</option><option value="pending">На проверке</option><option value="rejected">Отклонено</option></select></div></div>
            <div class="mt-3"><label class="form-label" for="note">Комментарий</label><textarea class="form-control" id="note" name="note" rows="3"></textarea></div>
            <button class="btn btn-gold w-100 mt-4" type="submit"><i class="bi bi-person-check me-1"></i>Назначить</button>
        </form>
    </div>

    <div class="col-xl-7">
        <form class="card-soft p-4" method="GET" action="{{ route('admin.representatives.index') }}">
            <h2 class="h5 mb-4">Фильтры</h2>
            <div class="row g-3 align-items-end"><div class="col-md-7"><label class="form-label" for="q">Поиск</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Пользователь, email или объект"></div><div class="col-md-3"><label class="form-label" for="filter_status">Статус</label><select class="form-select" id="filter_status" name="status"><option value="">Все</option><option value="pending" @selected(($filters['status'] ?? '') === 'pending')>На проверке</option><option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Подтверждено</option><option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Отклонено</option></select></div><div class="col-md-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-search"></i></button></div></div>
        </form>
    </div>
</div>

<div class="d-grid gap-3">
    @forelse($assignments as $assignment)
        <article class="card-soft p-4">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5"><div class="d-flex flex-wrap gap-2 mb-3"><span class="badge rounded-pill {{ $assignment->status === 'approved' ? 'badge-published' : 'badge-draft' }}">{{ ['approved' => 'Подтверждено', 'pending' => 'На проверке', 'rejected' => 'Отклонено'][$assignment->status] ?? $assignment->status }}</span><span class="badge rounded-pill text-bg-light">{{ $assignment->role === 'manager' ? 'Управляющий' : 'Редактор' }}</span></div><h2 class="h5 mb-2">{{ optional($assignment->pilgrimageObject)->name }}</h2><div class="small text-secondary mb-2">{{ optional($assignment->user)->name }} · {{ optional($assignment->user)->email }}</div>@if($assignment->note)<p class="small mb-0">{{ $assignment->note }}</p>@endif</div>
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('admin.representatives.update', $assignment) }}">@csrf @method('PUT')<div class="row g-3"><div class="col-md-3"><label class="form-label">Роль</label><select class="form-select" name="role"><option value="editor" @selected($assignment->role === 'editor')>Редактор</option><option value="manager" @selected($assignment->role === 'manager')>Управляющий</option></select></div><div class="col-md-3"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="pending" @selected($assignment->status === 'pending')>На проверке</option><option value="approved" @selected($assignment->status === 'approved')>Подтверждено</option><option value="rejected" @selected($assignment->status === 'rejected')>Отклонено</option></select></div><div class="col-md-6"><label class="form-label">Комментарий</label><input class="form-control" name="note" value="{{ $assignment->note }}"></div><div class="col-12"><button class="btn btn-sm btn-gold" type="submit">Сохранить</button></div></div></form>
                    <form class="mt-2" method="POST" action="{{ route('admin.representatives.destroy', $assignment) }}" onsubmit="return confirm('Отозвать доступ к объекту?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Отозвать доступ</button></form>
                </div>
            </div>
        </article>
    @empty<div class="card-soft p-5 text-center text-secondary">Представители пока не назначены.</div>@endforelse
</div>

@if($assignments->hasPages())<div class="mt-4">{{ $assignments->links() }}</div>@endif
@endsection
