@extends('admin.layouts.app')

@section('title', 'Изменения от представителей')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="page-title"><i class="bi bi-building-check me-2"></i>Изменения от представителей</h1><div class="page-subtitle">Проверка данных карточек и материалов, отправленных храмами и паломническими службами.</div></div>
    <a class="btn btn-outline-green" href="{{ route('admin.representatives.index') }}"><i class="bi bi-person-badge me-1"></i>Представители</a>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.service-review.index') }}">
    <div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="status">Статус</label><select class="form-select" id="status" name="status"><option value="pending" @selected($status === 'pending')>На проверке</option><option value="approved" @selected($status === 'approved')>Одобрено</option><option value="rejected" @selected($status === 'rejected')>Отклонено</option></select></div><div class="col-md-4"><label class="form-label" for="type">Тип</label><select class="form-select" id="type" name="type"><option value="">Все</option><option value="updates" @selected(($filters['type'] ?? '') === 'updates')>Изменения карточек</option><option value="media" @selected(($filters['type'] ?? '') === 'media')>Медиаматериалы</option></select></div><div class="col-md-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button></div></div>
</form>

@if(($filters['type'] ?? '') !== 'media')
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-end mb-3"><div><div class="small text-uppercase text-secondary mb-1">Карточки объектов</div><h2 class="h4 mb-0">Заявки на изменение</h2></div><span class="small text-secondary">{{ $updates->total() }} записей</span></div>
    <div class="d-grid gap-3">
        @forelse($updates as $updateRequest)
            @php($payload = $updateRequest->payload)
            <article class="card-soft p-4">
                <div class="row g-4">
                    <div class="col-xl-4"><span class="badge rounded-pill {{ $updateRequest->status === 'approved' ? 'badge-published' : 'badge-draft' }} mb-3">{{ $updateRequest->status }}</span><h3 class="h5 mb-2">{{ optional($updateRequest->pilgrimageObject)->name }}</h3><div class="small text-secondary mb-2">Отправил: {{ optional($updateRequest->user)->name }} · {{ optional($updateRequest->user)->email }}</div><div class="small text-secondary">{{ $updateRequest->created_at->format('d.m.Y H:i') }}</div>@if($updateRequest->review_note)<div class="alert alert-light small mt-3 mb-0">{{ $updateRequest->review_note }}</div>@endif</div>
                    <div class="col-xl-5"><div class="row g-2 small">@foreach(['address' => 'Адрес','phone' => 'Телефон','email' => 'Email','website' => 'Сайт','schedule_text' => 'Расписание','parking_info' => 'Парковка','accessibility_info' => 'Доступность','short_description' => 'Краткое описание','description' => 'Описание','history' => 'История'] as $key => $label)@if(array_key_exists($key, $payload))<div class="col-12"><div class="border rounded-3 p-3"><div class="text-secondary mb-1">{{ $label }}</div><div style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit((string)$payload[$key], 700) ?: '—' }}</div></div></div>@endif @endforeach</div></div>
                    <div class="col-xl-3">
                        @if($updateRequest->status === 'pending')
                            <form method="POST" action="{{ route('admin.service-review.requests.update', $updateRequest) }}">@csrf @method('PUT')<label class="form-label" for="note-{{ $updateRequest->id }}">Комментарий</label><textarea class="form-control" id="note-{{ $updateRequest->id }}" name="review_note" rows="4"></textarea><div class="d-grid gap-2 mt-3"><button class="btn btn-gold" type="submit" name="status" value="approved"><i class="bi bi-check-lg me-1"></i>Одобрить и опубликовать</button><button class="btn btn-outline-danger" type="submit" name="status" value="rejected"><i class="bi bi-x-lg me-1"></i>Отклонить</button></div></form>
                        @else<div class="small text-secondary">Заявка уже рассмотрена {{ optional($updateRequest->reviewed_at)->format('d.m.Y H:i') }}.</div>@endif
                    </div>
                </div>
            </article>
        @empty<div class="card-soft p-5 text-center text-secondary">Заявок на изменение с выбранным статусом нет.</div>@endforelse
    </div>
    @if($updates->hasPages())<div class="mt-4">{{ $updates->links() }}</div>@endif
</section>
@endif

@if(($filters['type'] ?? '') !== 'updates')
<section>
    <div class="d-flex justify-content-between align-items-end mb-3"><div><div class="small text-uppercase text-secondary mb-1">Галерея и документы</div><h2 class="h4 mb-0">Медиаматериалы</h2></div><span class="small text-secondary">{{ $media->total() }} записей</span></div>
    <div class="row g-4">
        @forelse($media as $submission)
            <div class="col-md-6 col-xl-4">
                <article class="card-soft p-3 h-100">
                    @if($submission->type === 'image' && $submission->status !== 'rejected')<img src="{{ $submission->url }}" alt="{{ $submission->title }}" class="media-preview mb-3">@else<div class="media-preview d-flex align-items-center justify-content-center mb-3"><i class="bi bi-file-earmark fs-1"></i></div>@endif
                    <span class="badge rounded-pill {{ $submission->status === 'approved' ? 'badge-published' : 'badge-draft' }} mb-2">{{ $submission->status }}</span>
                    <h3 class="h6 mb-2">{{ $submission->title ?: 'Без названия' }}</h3><div class="small text-secondary mb-1">{{ optional($submission->pilgrimageObject)->name }}</div><div class="small text-secondary mb-3">{{ optional($submission->user)->name }} · {{ $submission->type }}</div>@if($submission->description)<p class="small">{{ $submission->description }}</p>@endif
                    @if($submission->status === 'pending')<form method="POST" action="{{ route('admin.service-review.media.update', $submission) }}">@csrf @method('PUT')<textarea class="form-control mb-2" name="review_note" rows="2" placeholder="Комментарий"></textarea><div class="d-flex gap-2"><button class="btn btn-sm btn-gold flex-grow-1" type="submit" name="status" value="approved">Одобрить</button><button class="btn btn-sm btn-outline-danger" type="submit" name="status" value="rejected">Отклонить</button></div></form>@endif
                </article>
            </div>
        @empty<div class="col-12"><div class="card-soft p-5 text-center text-secondary">Материалов с выбранным статусом нет.</div></div>@endforelse
    </div>
    @if($media->hasPages())<div class="mt-4">{{ $media->links() }}</div>@endif
</section>
@endif
@endsection
