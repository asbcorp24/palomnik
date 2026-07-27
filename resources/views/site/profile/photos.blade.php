@extends('site.profile.layout')

@section('title', 'Паломнические фото — Московский паломник')
@section('profile_title', 'Паломнические фото')
@section('profile_subtitle', 'Личная фотолетопись поездок с возможностью отправить выбранные снимки на общий сайт.')

@section('profile_content')
<div class="profile-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="section-kicker mb-1">Новая фотография</div>
            <h2 class="h4 mb-1">Добавить в личную галерею</h2>
            <div class="small text-secondary">Крупные изображения автоматически уменьшаются пропорционально до {{ config('palomnik.images.max_width', 1920) }}×{{ config('palomnik.images.max_height', 1080) }}.</div>
        </div>
        <a class="btn btn-outline-pm" href="{{ route('community.photos') }}"><i class="bi bi-images me-2"></i>Общая галерея</a>
    </div>

    <form method="POST" action="{{ route('profile.photos.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="photo-file">Фотография</label>
                <input class="form-control" id="photo-file" name="file" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="photo-route">Паломнический маршрут</label>
                <select class="form-select" id="photo-route" name="pilgrimage_route_id">
                    <option value="">Выбрать позднее</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}" @selected((int) old('pilgrimage_route_id') === $route->id)>{{ $route->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="photo-title">Название</label>
                <input class="form-control" id="photo-title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Например: Утро у монастыря">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="photo-description">Описание</label>
                <input class="form-control" id="photo-description" name="description" value="{{ old('description') }}" maxlength="3000" placeholder="Краткая история снимка">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" id="request-publication" name="request_publication" type="checkbox" value="1" @checked(old('request_publication'))>
                    <label class="form-check-label" for="request-publication">Сразу отправить фотографию на модерацию для публикации на общем сайте</label>
                </div>
                <div class="form-text">Для публичной фотографии обязательно выберите маршрут. До одобрения снимок виден только вам и модератору.</div>
            </div>
        </div>
        <button class="btn btn-pm-gold mt-4" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i>Загрузить фотографию</button>
    </form>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div><div class="section-kicker mb-1">Фотолетопись</div><h2 class="h4 mb-0">Мои фотографии</h2></div>
    <div class="small text-secondary">Всего: {{ $photos->total() }}</div>
</div>

<div class="row g-4">
    @forelse($photos as $photo)
        @php
            $statusClass = match($photo->status) {
                'published' => 'text-bg-success',
                'pending' => 'text-bg-warning',
                'rejected' => 'text-bg-danger',
                default => 'text-bg-secondary',
            };
        @endphp
        <div class="col-md-6 col-xl-4">
            <article class="card-pm h-100 overflow-hidden">
                <img class="w-100" src="{{ $photo->url }}" alt="{{ $photo->title ?: 'Паломническая фотография' }}" style="height:230px;object-fit:cover">
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h3 class="h6 mb-0">{{ $photo->title ?: 'Фотография #'.$photo->id }}</h3>
                        <span class="badge rounded-pill {{ $statusClass }}">{{ $statusLabels[$photo->status] ?? $photo->status }}</span>
                    </div>
                    <div class="small text-secondary mb-2"><i class="bi bi-signpost-split me-1"></i>{{ optional($photo->pilgrimageRoute)->title ?: 'Маршрут не выбран' }}</div>
                    @if($photo->description)<p class="small text-secondary">{{ $photo->description }}</p>@endif
                    @if($photo->status === 'rejected' && $photo->moderation_notes)<div class="alert alert-danger py-2 small">Причина: {{ $photo->moderation_notes }}</div>@endif

                    <details class="mb-3">
                        <summary class="small fw-semibold" style="cursor:pointer">Изменить описание и маршрут</summary>
                        <form class="mt-3" method="POST" action="{{ route('profile.photos.update', $photo) }}">
                            @csrf
                            @method('PUT')
                            <input class="form-control form-control-sm mb-2" name="title" value="{{ $photo->title }}" maxlength="255" placeholder="Название">
                            <textarea class="form-control form-control-sm mb-2" name="description" rows="2" maxlength="3000" placeholder="Описание">{{ $photo->description }}</textarea>
                            <select class="form-select form-select-sm mb-2" name="pilgrimage_route_id">
                                <option value="">Без маршрута</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route->id }}" @selected($photo->pilgrimage_route_id === $route->id)>{{ $route->title }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-pm" type="submit">Сохранить</button>
                        </form>
                    </details>

                    @if(in_array($photo->status, ['private', 'rejected'], true))
                        <form class="mb-2" method="POST" action="{{ route('profile.photos.publish', $photo) }}">
                            @csrf
                            <select class="form-select form-select-sm mb-2" name="pilgrimage_route_id" required>
                                <option value="">Выберите маршрут для публикации</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route->id }}" @selected($photo->pilgrimage_route_id === $route->id)>{{ $route->title }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-pm-green w-100" type="submit"><i class="bi bi-send-check me-1"></i>Отправить на публикацию</button>
                        </form>
                    @else
                        <form class="mb-2" method="POST" action="{{ route('profile.photos.withdraw', $photo) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Снять с публикации</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('profile.photos.destroy', $photo) }}" onsubmit="return confirm('Удалить фотографию без возможности восстановления?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger w-100" type="submit"><i class="bi bi-trash me-1"></i>Удалить</button>
                    </form>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><div class="empty-state py-5"><i class="bi bi-camera display-5 d-block mb-3"></i>В личной галерее пока нет фотографий.</div></div>
    @endforelse
</div>

@if($photos->hasPages())<div class="mt-4">{{ $photos->links() }}</div>@endif
@endsection
