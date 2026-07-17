@extends('site.layouts.app')

@section('title', $object->name.' — Московский паломник')
@section('meta_description', $object->short_description ?: 'Информация о паломническом объекте: '.$object->name)

@section('content')
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
                </div>
                <h1 class="section-title mb-3">{{ $object->name }}</h1>
                <p class="section-lead mb-0"><i class="bi bi-geo-alt me-2"></i>{{ $object->address }}</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a class="btn btn-pm-green" href="https://yandex.ru/maps/?rtext=~{{ $object->latitude }},{{ $object->longitude }}&rtt=auto" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right me-2"></i>Построить маршрут</a>
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                @if($object->coverMedia && $object->coverMedia->url)
                    <img class="detail-cover mb-5" src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}">
                @else
                    <div class="object-placeholder detail-placeholder mb-5"><i class="bi bi-buildings"></i></div>
                @endif

                @if($object->short_description)
                    <p class="fs-5 lh-lg mb-5">{{ $object->short_description }}</p>
                @endif

                @if($object->description)
                    <section class="mb-5">
                        <div class="section-kicker mb-2">О месте</div>
                        <h2 class="h2 mb-4">Описание</h2>
                        <div class="text-secondary lh-lg">{!! nl2br(e($object->description)) !!}</div>
                    </section>
                @endif

                @if($object->history)
                    <section class="mb-5">
                        <div class="section-kicker mb-2">Наследие</div>
                        <h2 class="h2 mb-4">История</h2>
                        <div class="text-secondary lh-lg">{!! nl2br(e($object->history)) !!}</div>
                    </section>
                @endif

                @if($object->sanctities->isNotEmpty())
                    <section class="mb-5">
                        <div class="section-kicker mb-2">Главное</div>
                        <h2 class="h2 mb-4">Святыни</h2>
                        <div class="row g-3">
                            @foreach($object->sanctities as $sanctity)
                                <div class="col-md-6">
                                    <div class="info-card h-100 d-flex gap-3">
                                        <span class="info-icon flex-shrink-0"><i class="bi bi-star"></i></span>
                                        <div>
                                            <h3 class="h6 mb-2">{{ $sanctity->name }}</h3>
                                            @if($sanctity->pivot->note)<div class="small text-secondary">{{ $sanctity->pivot->note }}</div>@endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @php($gallery = $object->media->where('type', 'image'))
                @if($gallery->isNotEmpty())
                    <section>
                        <div class="section-kicker mb-2">Галерея</div>
                        <h2 class="h2 mb-4">Фотографии</h2>
                        <div class="row g-3">
                            @foreach($gallery as $media)
                                <div class="col-6 col-md-4">
                                    <a href="{{ $media->url }}" target="_blank" rel="noopener">
                                        <img class="gallery-image" src="{{ $media->url }}" alt="{{ $media->title ?: $object->name }}" loading="lazy">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="col-lg-4">
                <div class="d-grid gap-3 position-sticky" style="top:105px">
                    @if($object->schedule_text)
                        <div class="info-card">
                            <div class="d-flex gap-3">
                                <span class="info-icon"><i class="bi bi-clock"></i></span>
                                <div><h2 class="h6 mb-2">Расписание</h2><div class="small text-secondary lh-lg">{!! nl2br(e($object->schedule_text)) !!}</div></div>
                            </div>
                        </div>
                    @endif
                    <div class="info-card">
                        <div class="d-flex gap-3">
                            <span class="info-icon"><i class="bi bi-telephone"></i></span>
                            <div>
                                <h2 class="h6 mb-2">Контакты</h2>
                                <div class="small d-grid gap-2">
                                    @if($object->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $object->phone) }}">{{ $object->phone }}</a>@endif
                                    @if($object->email)<a href="mailto:{{ $object->email }}">{{ $object->email }}</a>@endif
                                    @if($object->website)<a href="{{ $object->website }}" target="_blank" rel="noopener">Официальный сайт <i class="bi bi-box-arrow-up-right ms-1"></i></a>@endif
                                    @if(!$object->phone && !$object->email && !$object->website)<span class="text-secondary">Контакты уточняются.</span>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($object->parking_info || $object->accessibility_info)
                        <div class="info-card">
                            <h2 class="h6 mb-3">Условия посещения</h2>
                            @if($object->parking_info)<div class="small text-secondary mb-3"><i class="bi bi-p-circle me-2"></i>{{ $object->parking_info }}</div>@endif
                            @if($object->accessibility_info)<div class="small text-secondary"><i class="bi bi-universal-access me-2"></i>{{ $object->accessibility_info }}</div>@endif
                        </div>
                    @endif
                    <a class="btn btn-pm-gold py-3" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Открыть на общей карте</a>
                </div>
            </aside>
        </div>

        @if($similarObjects->isNotEmpty())
            <section class="mt-5 pt-5 border-top">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                    <div><div class="section-kicker mb-2">Продолжить знакомство</div><h2 class="h2 mb-0">Похожие места</h2></div>
                    <a class="btn btn-outline-pm" href="{{ route('objects.index', ['type' => optional($object->objectType)->slug]) }}">Смотреть все</a>
                </div>
                <div class="row g-4">
                    @foreach($similarObjects as $similarObject)
                        <div class="col-md-6 col-xl-4">@include('site.partials.object-card', ['object' => $similarObject])</div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</section>
@endsection
