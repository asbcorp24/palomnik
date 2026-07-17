@extends('admin.layouts.app')

@section('title', $object->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.objects.index') }}"><i class="bi bi-arrow-left me-1"></i>Храмы и объекты</a>
        <h1 class="page-title mt-2">{{ $object->name }}</h1>
        <div class="page-subtitle">{{ optional($object->objectType)->name }} · {{ $object->address }}</div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge rounded-pill align-self-center {{ $object->is_published ? 'badge-published' : 'badge-draft' }}">{{ $object->is_published ? 'Опубликован' : 'Черновик' }}</span>
        <a class="btn btn-gold" href="{{ route('admin.objects.edit', $object) }}"><i class="bi bi-pencil me-1"></i>Редактировать</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        @if($object->media->where('type', 'image')->isNotEmpty())
            <div class="card-soft p-3 mb-4">
                <div class="row g-2">
                    @foreach($object->media->where('type', 'image')->take(6) as $media)
                        <div class="{{ $loop->first ? 'col-12' : 'col-6 col-md-4' }}">
                            <img class="w-100 rounded-4" style="{{ $loop->first ? 'max-height:430px' : 'height:180px' }};object-fit:cover" src="{{ $media->url }}" alt="{{ $media->title }}">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-3">Описание</h2>
            <div class="text-secondary" style="white-space:pre-line">{{ $object->description ?: $object->short_description ?: 'Описание пока не заполнено.' }}</div>
        </div>

        @if($object->history)
            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-3">История</h2>
                <div class="text-secondary" style="white-space:pre-line">{{ $object->history }}</div>
            </div>
        @endif

        @if($object->schedule_text)
            <div class="card-soft p-4">
                <h2 class="h5 mb-3">Режим работы и расписание</h2>
                <div class="text-secondary" style="white-space:pre-line">{{ $object->schedule_text }}</div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-3">Сведения</h2>
            <dl class="row mb-0 small">
                <dt class="col-5 text-secondary">Тип</dt><dd class="col-7">{{ optional($object->objectType)->name ?? '—' }}</dd>
                <dt class="col-5 text-secondary">Викариатство</dt><dd class="col-7">{{ optional($object->vicariate)->name ?? '—' }}</dd>
                <dt class="col-5 text-secondary">Благочиние</dt><dd class="col-7">{{ optional($object->deanery)->name ?? '—' }}</dd>
                <dt class="col-5 text-secondary">Координаты</dt><dd class="col-7">{{ $object->latitude }}, {{ $object->longitude }}</dd>
                <dt class="col-5 text-secondary">Slug</dt><dd class="col-7"><code>{{ $object->slug }}</code></dd>
            </dl>
        </div>

        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-3">Контакты</h2>
            <div class="small d-grid gap-2">
                <div><i class="bi bi-geo-alt me-2 text-secondary"></i>{{ $object->address }}</div>
                @if($object->phone)<div><i class="bi bi-telephone me-2 text-secondary"></i>{{ $object->phone }}</div>@endif
                @if($object->email)<div><i class="bi bi-envelope me-2 text-secondary"></i><a href="mailto:{{ $object->email }}">{{ $object->email }}</a></div>@endif
                @if($object->website)<div><i class="bi bi-globe me-2 text-secondary"></i><a href="{{ $object->website }}" target="_blank" rel="noopener">Открыть сайт</a></div>@endif
            </div>
        </div>

        <div class="card-soft p-4">
            <h2 class="h5 mb-3">Святыни</h2>
            @forelse($object->sanctities as $sanctity)
                <div class="border rounded-3 p-2 mb-2">
                    <div class="fw-semibold small">{{ $sanctity->name }}</div>
                    @if($sanctity->type)<div class="small text-secondary">{{ $sanctity->type }}</div>@endif
                </div>
            @empty
                <div class="small text-secondary">Святыни не указаны.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
