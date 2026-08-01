@extends('admin.layouts.app')

@section('title', 'Возможные дубли')

@push('styles')
<style>
    .duplicate-object-card { border:1px solid rgba(111,77,55,.14);border-radius:16px;padding:16px;background:#fffdf9;height:100%; }
    .duplicate-score { min-width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(176,138,62,.13);color:#7a5b1f;font-size:1.25rem;font-weight:800; }
    .duplicate-reason { display:inline-flex;align-items:center;padding:.38rem .65rem;border-radius:999px;background:rgba(38,68,59,.09);color:#26443b;font-size:.78rem; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Возможные дубли</h1>
        <div class="page-subtitle">Сравнение объектов по названию, координатам, телефону и сайту.</div>
    </div>
    <form method="POST" action="{{ route('admin.duplicates.scan') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<span class=&quot;spinner-border spinner-border-sm me-2&quot;></span>Проверяем...';">
        @csrf
        <button class="btn btn-gold" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Пересчитать дубли</button>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach($statuses as $value => $label)
        <div class="col-6 col-md-4 col-xl">
            <a class="card-soft stat-card d-block text-decoration-none text-reset {{ ($filters['status'] ?? '') === $value ? 'border-warning' : '' }}" href="{{ route('admin.duplicates.index', ['status' => $value]) }}">
                <span class="stat-icon"><i class="bi {{ $value === 'pending' ? 'bi-exclamation-diamond' : ($value === 'merged' ? 'bi-intersect' : 'bi-check2-circle') }}"></i></span>
                <div class="stat-number">{{ (int)($stats[$value] ?? 0) }}</div>
                <div class="stat-label">{{ $label }}</div>
            </a>
        </div>
    @endforeach
</div>

<div class="card-soft p-3 mb-4">
    <form class="row g-3 align-items-end" method="GET" action="{{ route('admin.duplicates.index') }}">
        <div class="col-lg-5">
            <label class="form-label" for="q">Поиск по названию или адресу</label>
            <input class="form-control" id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название храма">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="status">Статус</label>
            <select class="form-select" id="status" name="status">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? 'pending') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="form-label" for="min_score">Минимальная оценка</label>
            <select class="form-select" id="min_score" name="min_score">
                <option value="">Любая</option>
                @foreach([50,60,70,80,90] as $score)
                    <option value="{{ $score }}" @selected((string)($filters['min_score'] ?? '') === (string)$score)>от {{ $score }}%</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-lg-2 d-grid">
            <button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button>
        </div>
    </form>
</div>

@if($candidates->isEmpty())
    <div class="card-soft p-5 text-center text-secondary">
        <i class="bi bi-check2-circle display-4 d-block mb-3 text-success"></i>
        <h2 class="h5">Совпадений по выбранному фильтру нет</h2>
        <p class="mb-0">Нажмите «Пересчитать дубли», чтобы проверить актуальное состояние каталога.</p>
    </div>
@else
    <div class="d-grid gap-4">
        @foreach($candidates as $candidate)
            @php($first = $candidate->objectA)
            @php($second = $candidate->objectB)
            <article class="card-soft p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="duplicate-score">{{ number_format((float)$candidate->score, 0) }}%</div>
                        <div>
                            <div class="fw-semibold">Кандидат №{{ $candidate->id }}</div>
                            <div class="small text-secondary">Похожесть названий: {{ number_format((float)$candidate->name_similarity, 0) }}%@if($candidate->distance_meters !== null) · расстояние {{ $candidate->distance_meters }} м@endif</div>
                        </div>
                    </div>
                    <span class="badge rounded-pill text-bg-light">{{ $statuses[$candidate->status] ?? $candidate->status }}</span>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach($candidate->reasons ?? [] as $reason)<span class="duplicate-reason"><i class="bi bi-check2 me-1"></i>{{ $reason }}</span>@endforeach
                </div>

                <div class="row g-3 mb-4">
                    @foreach([['key'=>'a','object'=>$first],['key'=>'b','object'=>$second]] as $side)
                        @php($object = $side['object'])
                        <div class="col-lg-6">
                            <div class="duplicate-object-card">
                                @if(!$object)
                                    <div class="text-danger">Объект больше не существует.</div>
                                @else
                                    <div class="d-flex gap-3 align-items-start mb-3">
                                        @if($object->coverMedia?->url)
                                            <img class="object-thumb" src="{{ $object->coverMedia->url }}" alt="">
                                        @else
                                            <span class="object-thumb d-flex align-items-center justify-content-center"><i class="bi bi-image text-secondary"></i></span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="small text-secondary">ID {{ $object->id }} · {{ $object->objectType?->name ?: 'Без типа' }}</div>
                                            <h2 class="h5 mt-1 mb-1">{{ $object->name }}</h2>
                                            <code class="small">{{ $object->slug }}</code>
                                        </div>
                                    </div>
                                    <dl class="row small mb-3">
                                        <dt class="col-4 text-secondary">Адрес</dt><dd class="col-8">{{ $object->address ?: '—' }}</dd>
                                        <dt class="col-4 text-secondary">Телефон</dt><dd class="col-8">{{ $object->phone ?: '—' }}</dd>
                                        <dt class="col-4 text-secondary">Сайт</dt><dd class="col-8 text-break">{{ $object->website ?: '—' }}</dd>
                                        <dt class="col-4 text-secondary">Координаты</dt><dd class="col-8">{{ $object->latitude }}, {{ $object->longitude }}</dd>
                                        <dt class="col-4 text-secondary">Родитель</dt><dd class="col-8">{{ $object->parentObject?->name ?: '—' }}</dd>
                                        <dt class="col-4 text-secondary">Статус</dt><dd class="col-8">{{ $object->is_published ? 'Опубликован' : 'Черновик' }}@if($object->trashed()) · архив@endif</dd>
                                    </dl>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-light" href="{{ route('admin.objects.show', $object) }}"><i class="bi bi-eye me-1"></i>Карточка</a>
                                        <a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}"><i class="bi bi-pencil me-1"></i>Редактировать</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($candidate->status === 'pending' && $first && $second)
                    <div class="border-top pt-4">
                        <div class="row g-3">
                            <div class="col-xl-4">
                                <form method="POST" action="{{ route('admin.duplicates.merge', $candidate) }}" onsubmit="return confirm('Объединить два объекта? Связанные данные будут перенесены, второй объект попадёт в архив.')">
                                    @csrf
                                    <label class="form-label small">Объединить, оставить основной записью</label>
                                    <div class="input-group">
                                        <select class="form-select" name="master_id" required>
                                            <option value="{{ $first->id }}">{{ $first->name }}</option>
                                            <option value="{{ $second->id }}">{{ $second->name }}</option>
                                        </select>
                                        <button class="btn btn-danger" type="submit"><i class="bi bi-intersect me-1"></i>Объединить</button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-xl-4">
                                <form method="POST" action="{{ route('admin.duplicates.parent', $candidate) }}">
                                    @csrf
                                    <label class="form-label small">Установить родительский объект</label>
                                    <div class="input-group">
                                        <select class="form-select" name="parent_id" required>
                                            <option value="{{ $first->id }}">Родитель: {{ $first->name }}</option>
                                            <option value="{{ $second->id }}">Родитель: {{ $second->name }}</option>
                                        </select>
                                        <button class="btn btn-outline-green" type="submit"><i class="bi bi-diagram-2 me-1"></i>Связать</button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-xl-4">
                                <label class="form-label small">Не объединять</label>
                                <div class="d-flex gap-2">
                                    <form class="flex-grow-1" method="POST" action="{{ route('admin.duplicates.mark', $candidate) }}">@csrf<input type="hidden" name="status" value="separate"><button class="btn btn-light w-100" type="submit">Оставить отдельно</button></form>
                                    <form class="flex-grow-1" method="POST" action="{{ route('admin.duplicates.mark', $candidate) }}">@csrf<input type="hidden" name="status" value="ignored"><button class="btn btn-light w-100" type="submit">Игнорировать</button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($candidate->reviewer)
                    <div class="small text-secondary border-top pt-3">Решение: {{ $candidate->reviewer->name }} · {{ optional($candidate->reviewed_at)->format('d.m.Y H:i') }}</div>
                @endif
            </article>
        @endforeach
    </div>

    @if($candidates->hasPages())<div class="mt-4">{{ $candidates->links() }}</div>@endif
@endif
@endsection
