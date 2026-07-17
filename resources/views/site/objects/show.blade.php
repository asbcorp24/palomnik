@extends('site.layouts.app')

@section('title', $object->name.' — Московский паломник')
@section('meta_description', $object->short_description ?: 'Информация о паломническом объекте: '.$object->name)

@section('content')
@php
    $destination = $object->latitude.','.$object->longitude;
    $routeLinks = [
        'pd' => 'https://yandex.ru/maps/?mode=routes&rtext='.rawurlencode('~'.$destination).'&rtt=pd',
        'mt' => 'https://yandex.ru/maps/?mode=routes&rtext='.rawurlencode('~'.$destination).'&rtt=mt',
        'auto' => 'https://yandex.ru/maps/?mode=routes&rtext='.rawurlencode('~'.$destination).'&rtt=auto',
    ];
    $officialImages = $object->media->where('type', 'image');
    $videos = $object->media->where('type', 'video');
    $audio = $object->media->where('type', 'audio');
    $documents = $object->media->where('type', 'document');
    $communityImages = $object->userMedia->where('type', 'image');
@endphp
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-3">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('objects.index') }}">Храмы и святыни</a></li>
                <li class="breadcrumb-item active">{{ $object->name }}</li>
            </ol>
        </nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge rounded-pill object-type-badge">{{ optional($object->objectType)->name ?: 'Паломнический объект' }}</span>
                    @if($object->vicariate)<span class="badge rounded-pill text-bg-light">{{ $object->vicariate->name }}</span>@endif
                    @if($object->deanery)<span class="badge rounded-pill text-bg-light">{{ $object->deanery->name }}</span>@endif
                    @if($rating)<span class="badge rounded-pill text-bg-light"><i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($rating, 1, ',', ' ') }} · {{ $object->reviews->count() }}</span>@endif
                </div>
                <h1 class="section-title mb-3">{{ $object->name }}</h1>
                <p class="section-lead mb-0"><i class="bi bi-geo-alt me-2"></i>{{ $object->address }}</p>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                    @auth
                        <form class="d-flex gap-2" method="POST" action="{{ route('favorites.objects.add', $object) }}">
                            @csrf
                            @if($favoriteLists->count() > 1)
                                <select class="form-select form-select-sm" name="favorite_list_id" style="max-width:170px">@foreach($favoriteLists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach</select>
                            @endif
                            <button class="btn {{ $isFavorite ? 'btn-pm-gold' : 'btn-outline-pm' }}" type="submit"><i class="bi {{ $isFavorite ? 'bi-heart-fill' : 'bi-heart' }} me-1"></i>{{ $isFavorite ? 'Сохранено' : 'В избранное' }}</button>
                        </form>
                    @else
                        <a class="btn btn-outline-pm" href="{{ route('login') }}"><i class="bi bi-heart me-1"></i>В избранное</a>
                    @endauth
                    <button class="btn btn-light" id="saveOfflineButton" type="button"><i class="bi bi-download me-1"></i>Офлайн</button>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                @if($object->coverMedia && $object->coverMedia->url)
                    <img class="detail-cover mb-5 offline-asset" src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}">
                @else
                    <div class="object-placeholder detail-placeholder mb-5"><i class="bi bi-buildings"></i></div>
                @endif

                @if($object->short_description)<p class="fs-5 lh-lg mb-5">{{ $object->short_description }}</p>@endif

                @if($object->description)
                    <section class="mb-5"><div class="section-kicker mb-2">О месте</div><h2 class="h2 mb-4">Описание</h2><div class="text-secondary lh-lg">{!! nl2br(e($object->description)) !!}</div></section>
                @endif

                @if($object->history)
                    <section class="mb-5"><div class="section-kicker mb-2">Наследие</div><h2 class="h2 mb-4">История</h2><div class="text-secondary lh-lg">{!! nl2br(e($object->history)) !!}</div></section>
                @endif

                @if($object->sanctities->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Главное</div><h2 class="h2 mb-4">Святыни</h2><div class="row g-3">@foreach($object->sanctities as $sanctity)<div class="col-md-6"><div class="info-card h-100 overflow-hidden p-0">@if($sanctity->image_url)<img src="{{ $sanctity->image_url }}" alt="{{ $sanctity->name }}" class="w-100 offline-asset" style="height:190px;object-fit:cover" loading="lazy">@endif<div class="p-4 d-flex gap-3"><span class="info-icon flex-shrink-0"><i class="bi bi-star"></i></span><div><h3 class="h6 mb-2">{{ $sanctity->name }}</h3>@if($sanctity->description)<div class="small text-secondary mb-2">{{ $sanctity->description }}</div>@endif@if($sanctity->pivot->note)<div class="small text-secondary">{{ $sanctity->pivot->note }}</div>@endif</div></div></div></div>@endforeach</div></section>
                @endif

                @if($officialImages->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Галерея</div><h2 class="h2 mb-4">Фотографии</h2><div class="row g-3">@foreach($officialImages as $media)<div class="col-6 col-md-4"><a href="{{ $media->url }}" target="_blank" rel="noopener"><img class="gallery-image offline-asset" src="{{ $media->url }}" alt="{{ $media->title ?: $object->name }}" loading="lazy"></a></div>@endforeach</div></section>
                @endif

                @if($videos->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Видео</div><h2 class="h2 mb-4">Материалы об объекте</h2><div class="row g-4">@foreach($videos as $media)<div class="col-md-6"><video class="w-100 rounded-4" controls preload="metadata" src="{{ $media->url }}"></video>@if($media->title)<div class="small text-secondary mt-2">{{ $media->title }}</div>@endif</div>@endforeach</div></section>
                @endif

                @if($audio->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Аудиогид</div><h2 class="h2 mb-4">Слушать</h2><div class="d-grid gap-3">@foreach($audio as $media)<div class="info-card"><div class="fw-semibold mb-2">{{ $media->title ?: 'Аудиоматериал' }}</div><audio class="w-100 offline-asset" controls preload="metadata" src="{{ $media->url }}"></audio></div>@endforeach</div></section>
                @endif

                @if($documents->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Путеводитель</div><h2 class="h2 mb-4">Схемы и документы</h2><div class="d-grid gap-2">@foreach($documents as $media)<a class="info-card d-flex align-items-center gap-3 text-decoration-none offline-link" href="{{ $media->url }}" target="_blank" rel="noopener"><span class="info-icon"><i class="bi bi-file-earmark-map"></i></span><span><strong class="d-block">{{ $media->title ?: 'Открыть документ' }}</strong><small class="text-secondary">{{ $media->description }}</small></span></a>@endforeach</div></section>
                @endif

                @if($communityImages->isNotEmpty())
                    <section class="mb-5"><div class="section-kicker mb-2">Сообщество</div><h2 class="h2 mb-4">Фотографии паломников</h2><div class="row g-3">@foreach($communityImages as $media)<div class="col-6 col-md-4"><img class="gallery-image" src="{{ $media->url }}" alt="{{ $media->title ?: $object->name }}" loading="lazy"><div class="small text-secondary mt-2">{{ optional($media->user)->name }}</div></div>@endforeach</div></section>
                @endif

                <section id="reviews" class="mt-5 pt-5 border-top">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="section-kicker mb-2">Впечатления</div><h2 class="h2 mb-0">Отзывы</h2></div>@if($rating)<div class="fs-5"><span class="review-stars">★</span> <strong>{{ number_format($rating, 1, ',', ' ') }}</strong></div>@endif</div>

                    @auth
                        <form class="filter-card mb-4" method="POST" action="{{ route('reviews.store', $object) }}">
                            @csrf
                            <h3 class="h5 mb-3">{{ $userReview ? 'Изменить свой отзыв' : 'Оставить отзыв' }}</h3>
                            <div class="rating-input mb-3">
                                @for($star = 5; $star >= 1; $star--)<input id="star{{ $star }}" name="rating" type="radio" value="{{ $star }}" @checked((int)old('rating', optional($userReview)->rating) === $star) required><label for="star{{ $star }}">★</label>@endfor
                            </div>
                            <textarea class="form-control mb-3" name="body" rows="4" minlength="10" maxlength="5000" placeholder="Расскажите о посещении" required>{{ old('body', optional($userReview)->body) }}</textarea>
                            <button class="btn btn-pm-gold" type="submit">Отправить на модерацию</button>
                        </form>
                    @else
                        <div class="filter-card mb-4">Чтобы оставить отзыв, <a href="{{ route('login') }}">войдите</a> или <a href="{{ route('register') }}">зарегистрируйтесь</a>.</div>
                    @endauth

                    <div class="d-grid gap-3">
                        @forelse($object->reviews as $review)
                            <article class="review-card"><div class="d-flex justify-content-between gap-3 mb-3"><div><strong>{{ optional($review->user)->name }}</strong><div class="small text-secondary">{{ $review->created_at->format('d.m.Y') }}</div></div><div class="review-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div></div><div class="text-secondary lh-lg">{{ $review->body }}</div></article>
                        @empty
                            <div class="text-secondary">Опубликованных отзывов пока нет.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="col-lg-4">
                <div class="d-grid gap-3 position-sticky" style="top:105px">
                    <div class="info-card">
                        <h2 class="h5 mb-3">Построить маршрут</h2>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-pm" href="{{ $routeLinks['pd'] }}" target="_blank" rel="noopener"><i class="bi bi-person-walking me-2"></i>Пешком</a>
                            <a class="btn btn-outline-pm" href="{{ $routeLinks['mt'] }}" target="_blank" rel="noopener"><i class="bi bi-bus-front me-2"></i>Общественный транспорт</a>
                            <a class="btn btn-pm-green" href="{{ $routeLinks['auto'] }}" target="_blank" rel="noopener"><i class="bi bi-car-front me-2"></i>На автомобиле</a>
                        </div>
                        @if($object->parking_info)<div class="small text-secondary mt-3"><i class="bi bi-p-circle me-1"></i>{{ $object->parking_info }}</div>@endif
                    </div>

                    @if($object->schedule_text)<div class="info-card"><div class="d-flex gap-3"><span class="info-icon"><i class="bi bi-clock"></i></span><div><h2 class="h6 mb-2">Расписание</h2><div class="small text-secondary lh-lg">{!! nl2br(e($object->schedule_text)) !!}</div></div></div></div>@endif

                    <div class="info-card"><div class="d-flex gap-3"><span class="info-icon"><i class="bi bi-telephone"></i></span><div><h2 class="h6 mb-2">Контакты</h2><div class="small d-grid gap-2">@if($object->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $object->phone) }}">{{ $object->phone }}</a>@endif @if($object->email)<a href="mailto:{{ $object->email }}">{{ $object->email }}</a>@endif @if($object->website)<a href="{{ $object->website }}" target="_blank" rel="noopener">Официальный сайт <i class="bi bi-box-arrow-up-right ms-1"></i></a>@endif @if(!$object->phone && !$object->email && !$object->website)<span class="text-secondary">Контакты уточняются.</span>@endif</div></div></div></div>

                    @if($object->accessibility_info)<div class="info-card"><h2 class="h6 mb-2">Доступность</h2><div class="small text-secondary"><i class="bi bi-universal-access me-2"></i>{{ $object->accessibility_info }}</div></div>@endif

                    @auth
                        <div class="info-card">
                            <h2 class="h5 mb-2">Я здесь</h2>
                            <p class="small text-secondary">Передайте геолокацию, чтобы подтвердить посещение и продвинуться к достижениям.</p>
                            <form id="visitForm" method="POST" action="{{ route('visits.store', $object) }}">@csrf<input id="visitLatitude" name="latitude" type="hidden"><input id="visitLongitude" name="longitude" type="hidden"><button class="btn btn-pm-gold w-100" id="visitButton" type="button"><i class="bi bi-geo-fill me-2"></i>Отметиться</button></form>
                            <div class="small text-secondary mt-2" id="visitStatus"></div>
                        </div>

                        <div class="info-card">
                            <h2 class="h5 mb-2">Поделиться фото</h2>
                            <form method="POST" action="{{ route('community.media.store') }}" enctype="multipart/form-data">@csrf<input name="pilgrimage_object_id" type="hidden" value="{{ $object->id }}"><input class="form-control form-control-sm mb-2" name="file" type="file" accept="image/*,video/*" required><input class="form-control form-control-sm mb-2" name="title" placeholder="Подпись"><button class="btn btn-outline-pm w-100" type="submit">Загрузить</button></form>
                        </div>
                    @else
                        <a class="btn btn-pm-gold py-3" href="{{ route('login') }}">Войти, чтобы отметиться</a>
                    @endauth

                    <a class="btn btn-outline-pm py-3" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Открыть на общей карте</a>
                </div>
            </aside>
        </div>

        @if($similarObjects->isNotEmpty())
            <section class="mt-5 pt-5 border-top"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="section-kicker mb-2">Продолжить знакомство</div><h2 class="h2 mb-0">Похожие места</h2></div><a class="btn btn-outline-pm" href="{{ route('objects.index', ['type' => optional($object->objectType)->slug]) }}">Смотреть все</a></div><div class="row g-4">@foreach($similarObjects as $similarObject)<div class="col-md-6 col-xl-4">@include('site.partials.object-card', ['object' => $similarObject])</div>@endforeach</div></section>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const visitButton = document.getElementById('visitButton');
    const visitForm = document.getElementById('visitForm');
    const status = document.getElementById('visitStatus');
    visitButton?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            status.textContent = 'Геолокация недоступна. Отметка будет отправлена на ручную проверку.';
            visitForm.submit();
            return;
        }
        visitButton.disabled = true;
        status.textContent = 'Определяем местоположение...';
        navigator.geolocation.getCurrentPosition(function (position) {
            document.getElementById('visitLatitude').value = position.coords.latitude;
            document.getElementById('visitLongitude').value = position.coords.longitude;
            visitForm.submit();
        }, function () {
            visitButton.disabled = false;
            status.textContent = 'Не удалось получить геолокацию. Разрешите доступ или повторите попытку.';
        }, {enableHighAccuracy:true, timeout:12000, maximumAge:30000});
    });

    document.getElementById('saveOfflineButton')?.addEventListener('click', async function () {
        const button = this;
        if (!('serviceWorker' in navigator)) {
            button.textContent = 'Не поддерживается';
            return;
        }
        const registration = await navigator.serviceWorker.ready;
        const urls = [location.href, ...Array.from(document.querySelectorAll('.offline-asset')).map(item => item.currentSrc || item.src), ...Array.from(document.querySelectorAll('.offline-link')).map(item => item.href)].filter(Boolean);
        registration.active?.postMessage({type:'CACHE_URLS', urls:[...new Set(urls)]});
        button.innerHTML = '<i class="bi bi-check-lg me-1"></i>Сохранено';
        button.classList.add('btn-success');
    });
})();
</script>
@endpush
