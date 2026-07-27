@extends('site.layouts.app')

@section('title', 'Паломнические фотографии — Московский паломник')
@section('meta_description', 'Проверенные фотографии паломников, привязанные к маршрутам Московского паломника.')

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Общая фотолетопись</div>
                <h1 class="section-title mb-3">Паломнические фотографии</h1>
                <p class="section-lead mb-0">Снимки участников, прошедшие проверку модератора и привязанные к паломническим маршрутам.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @auth<a class="btn btn-pm-gold" href="{{ route('profile.photos') }}"><i class="bi bi-camera me-2"></i>Добавить свои фото</a>@endauth
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-4" method="GET" action="{{ route('community.photos') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label" for="route">Маршрут</label>
                    <select class="form-select" id="route" name="route">
                        <option value="">Все маршруты</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->slug }}" @selected(($filters['route'] ?? '') === $route->slug)>{{ $route->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-grid"><button class="btn btn-pm-green" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button></div>
            </div>
        </form>

        <div class="row g-4">
            @forelse($photos as $photo)
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <article class="card-pm h-100 overflow-hidden">
                        <a href="{{ $photo->url }}" target="_blank" rel="noopener"><img class="w-100" src="{{ $photo->url }}" alt="{{ $photo->title ?: 'Паломническая фотография' }}" style="height:260px;object-fit:cover" loading="lazy"></a>
                        <div class="p-3">
                            <h2 class="h6 mb-2">{{ $photo->title ?: optional($photo->pilgrimageRoute)->title }}</h2>
                            @if($photo->pilgrimageRoute)<a class="small text-decoration-none d-block mb-2" href="{{ route('routes.show', $photo->pilgrimageRoute) }}"><i class="bi bi-signpost-split me-1"></i>{{ $photo->pilgrimageRoute->title }}</a>@endif
                            @if($photo->pilgrimageObject)<a class="small text-secondary text-decoration-none d-block mb-2" href="{{ route('objects.show', $photo->pilgrimageObject) }}"><i class="bi bi-geo-alt me-1"></i>{{ $photo->pilgrimageObject->name }}</a>@endif
                            @if($photo->description)<p class="small text-secondary mb-2">{{ \Illuminate\Support\Str::limit($photo->description, 130) }}</p>@endif
                            <div class="small text-secondary">Фото: {{ optional($photo->user)->name ?: 'Паломник' }}</div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="filter-card text-center py-5">Опубликованных фотографий по выбранному маршруту пока нет.</div></div>
            @endforelse
        </div>

        @if($photos->hasPages())<div class="mt-5">{{ $photos->links() }}</div>@endif
    </div>
</section>
@endsection
