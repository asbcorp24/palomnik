@extends('admin.layouts.app')

@section('title', 'Паломничество вместе')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title"><i class="bi bi-people-fill me-2"></i>Паломничество вместе</h1>
        <div class="page-subtitle">Модерация предложений пользователей о совместных паломничествах.</div>
    </div>
    <a class="btn btn-outline-green" href="{{ route('together.index') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Открыть раздел</a>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.together.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-7">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, организатор или место встречи">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Статус</label>
            <select class="form-select" id="status" name="status">
                <option value="">Все статусы</option>
                @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button></div>
    </div>
</form>

<div class="d-grid gap-3">
    @forelse($items as $item)
        <article class="card-soft p-4">
            <div class="row g-4 align-items-start">
                <div class="col-xl-7">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill {{ $item->status === 'published' ? 'badge-published' : 'badge-draft' }}">{{ $statuses[$item->status] ?? $item->status }}</span>
                        <span class="badge rounded-pill text-bg-light"><i class="bi bi-people me-1"></i>{{ $item->approved_members_count + 1 }} подтверждено</span>
                        @if($item->pending_members_count)<span class="badge rounded-pill text-bg-warning">{{ $item->pending_members_count }} заявок</span>@endif
                        <span class="badge rounded-pill text-bg-light"><i class="bi bi-chat-dots me-1"></i>{{ $item->messages_count }}</span>
                    </div>
                    <h2 class="h4 mb-2">{{ $item->title }}</h2>
                    <div class="small text-secondary mb-3">Организатор: {{ optional($item->organizer)->name }} · {{ optional($item->organizer)->email }}</div>
                    <div class="row g-3 small mb-3">
                        <div class="col-md-6"><strong>Начало:</strong> {{ $item->starts_at->format('d.m.Y H:i') }}</div>
                        <div class="col-md-6"><strong>Место встречи:</strong> {{ $item->meeting_place }}</div>
                        <div class="col-md-6"><strong>Маршрут:</strong> {{ optional($item->pilgrimageRoute)->title ?: 'не указан' }}</div>
                        <div class="col-md-6"><strong>Лимит:</strong> {{ $item->max_participants ?: 'без ограничения' }}</div>
                    </div>
                    <p class="text-secondary mb-3">{{ \Illuminate\Support\Str::limit($item->description, 300) }}</p>
                    <a class="btn btn-sm btn-light" href="{{ route('together.show', $item) }}" target="_blank" rel="noopener">Открыть предложение</a>
                </div>

                <div class="col-xl-5">
                    <form method="POST" action="{{ route('admin.together.update', $item) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="status-{{ $item->id }}">Статус</label>
                                <select class="form-select" id="status-{{ $item->id }}" name="status">
                                    @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="note-{{ $item->id }}">Комментарий модератора</label>
                                <textarea class="form-control" id="note-{{ $item->id }}" name="moderation_note" rows="4">{{ $item->moderation_note }}</textarea>
                            </div>
                            <div class="col-12"><button class="btn btn-gold" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить статус</button></div>
                        </div>
                    </form>
                    <form class="mt-2" method="POST" action="{{ route('admin.together.destroy', $item) }}" onsubmit="return confirm('Удалить предложение, заявки и обсуждение?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Удалить</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="card-soft p-5 text-center text-secondary"><i class="bi bi-people display-5 d-block mb-3"></i>Предложений пока нет.</div>
    @endforelse
</div>

@if($items->hasPages())<div class="mt-4">{{ $items->links() }}</div>@endif
@endsection
