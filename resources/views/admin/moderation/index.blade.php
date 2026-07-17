@extends('admin.layouts.app')

@section('title', $config['title'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title"><i class="bi {{ $config['icon'] }} me-2"></i>{{ $config['title'] }}</h1>
        <div class="page-subtitle">Проверка, подтверждение и изменение статусов.</div>
    </div>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.moderation.index', $resource) }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-7">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Пользователь, объект, билет или название">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Статус</label>
            <select class="form-select" id="status" name="status">
                <option value="">Все статусы</option>
                @foreach($config['statuses'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button>
        </div>
    </div>
</form>

<div class="d-grid gap-3">
    @forelse($items as $item)
        <article class="card-soft p-4">
            <div class="row g-4 align-items-start">
                <div class="col-xl-7">
                    @if($resource === 'bookings')
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="badge rounded-pill badge-draft">Бронирование #{{ $item->id }}</span>
                            @if($item->ticket_code)<span class="badge rounded-pill text-bg-light">Билет {{ $item->ticket_code }}</span>@endif
                        </div>
                        <h2 class="h5 mb-2">{{ optional(optional($item->trip)->pilgrimageRoute)->title ?: 'Маршрут не указан' }}</h2>
                        <div class="text-secondary small mb-3">
                            <i class="bi bi-calendar3 me-1"></i>{{ optional(optional($item->trip)->starts_at)->format('d.m.Y H:i') ?: 'Дата не указана' }}
                        </div>
                        <div class="row g-3 small">
                            <div class="col-md-6"><strong>Участник:</strong> {{ $item->contact_name }}</div>
                            <div class="col-md-6"><strong>Количество:</strong> {{ $item->participants_count }}</div>
                            <div class="col-md-6"><strong>Email:</strong> {{ $item->email ?: '—' }}</div>
                            <div class="col-md-6"><strong>Телефон:</strong> {{ $item->phone ?: '—' }}</div>
                            <div class="col-md-6"><strong>Сумма:</strong> {{ number_format((float)$item->total_amount, 2, ',', ' ') }} ₽</div>
                            <div class="col-md-6"><strong>Создано:</strong> {{ $item->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                    @elseif($resource === 'visits')
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="badge rounded-pill badge-draft">{{ $item->verification_method }}</span></div>
                        <h2 class="h5 mb-2">{{ optional($item->pilgrimageObject)->name ?: 'Объект удалён' }}</h2>
                        <div class="text-secondary small mb-3">{{ optional($item->user)->name }} · {{ optional($item->user)->email }}</div>
                        <div class="small"><strong>Время посещения:</strong> {{ optional($item->visited_at)->format('d.m.Y H:i') }}</div>
                        @if($item->latitude && $item->longitude)<div class="small mt-2"><strong>Координаты:</strong> {{ $item->latitude }}, {{ $item->longitude }}</div>@endif
                    @elseif($resource === 'reviews')
                        <div class="d-flex align-items-center gap-1 mb-3 text-warning">
                            @for($star = 1; $star <= 5; $star++)<i class="bi {{ $star <= $item->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor
                        </div>
                        <h2 class="h5 mb-2">{{ optional($item->pilgrimageObject)->name ?: 'Объект удалён' }}</h2>
                        <div class="text-secondary small mb-3">{{ optional($item->user)->name }} · {{ $item->created_at->format('d.m.Y H:i') }}</div>
                        <div class="lh-lg">{!! nl2br(e($item->body)) !!}</div>
                    @elseif($resource === 'posts')
                        <div class="small text-secondary mb-2">Автор: {{ optional($item->user)->name }} · {{ $item->created_at->format('d.m.Y H:i') }}</div>
                        <h2 class="h4 mb-3">{{ $item->title }}</h2>
                        @if($item->excerpt)<p class="text-secondary mb-3">{{ $item->excerpt }}</p>@endif
                        <details><summary class="fw-semibold" style="cursor:pointer">Показать полный текст</summary><div class="mt-3 lh-lg">{!! nl2br(e($item->body)) !!}</div></details>
                    @else
                        <div class="d-flex gap-3 align-items-start">
                            @if($item->type === 'image' && $item->url)
                                <img src="{{ $item->url }}" alt="{{ $item->title }}" style="width:160px;height:120px;object-fit:cover;border-radius:15px">
                            @else
                                <div class="object-thumb d-flex align-items-center justify-content-center" style="width:120px;height:100px"><i class="bi bi-camera-video fs-2"></i></div>
                            @endif
                            <div>
                                <div class="small text-secondary mb-2">{{ optional($item->user)->name }} · {{ strtoupper($item->type) }}</div>
                                <h2 class="h5 mb-2">{{ $item->title ?: 'Медиаматериал #'.$item->id }}</h2>
                                <div class="small text-secondary">Объект: {{ optional($item->pilgrimageObject)->name ?: 'не привязан' }}</div>
                                @if($item->url)<a class="btn btn-sm btn-light mt-3" href="{{ $item->url }}" target="_blank" rel="noopener">Открыть файл</a>@endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-xl-5">
                    <form method="POST" action="{{ route('admin.moderation.update', [$resource, $item->id]) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-{{ $resource === 'bookings' ? '6' : '12' }}">
                                <label class="form-label" for="status-{{ $item->id }}">Статус</label>
                                <select class="form-select" id="status-{{ $item->id }}" name="status">
                                    @foreach($config['statuses'] as $value => $label)
                                        <option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($resource === 'bookings')
                                <div class="col-md-6">
                                    <label class="form-label" for="payment-{{ $item->id }}">Оплата</label>
                                    <select class="form-select" id="payment-{{ $item->id }}" name="payment_status">
                                        @foreach($config['payment_statuses'] as $value => $label)
                                            <option value="{{ $value }}" @selected($item->payment_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if(in_array($resource, ['bookings', 'visits']))
                                <div class="col-12">
                                    <label class="form-label" for="notes-{{ $item->id }}">Комментарий администратора</label>
                                    <textarea class="form-control" id="notes-{{ $item->id }}" name="notes" rows="3">{{ $item->notes }}</textarea>
                                </div>
                            @endif
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button class="btn btn-gold" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить статус</button>
                            </div>
                        </div>
                    </form>
                    <form class="mt-2" method="POST" action="{{ route('admin.moderation.destroy', [$resource, $item->id]) }}" onsubmit="return confirm('Удалить запись без возможности восстановления?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Удалить</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="card-soft p-5 text-center text-secondary">
            <i class="bi {{ $config['icon'] }} display-5 d-block mb-3"></i>
            Записей для отображения нет.
        </div>
    @endforelse
</div>

@if($items->hasPages())
    <div class="mt-4">{{ $items->links() }}</div>
@endif
@endsection
