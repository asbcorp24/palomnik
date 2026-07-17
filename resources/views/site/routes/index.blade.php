@extends('site.layouts.app')

@section('title', 'Паломнические маршруты — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item active">Маршруты</li></ol></nav>
        <div class="section-kicker mb-2">Планирование паломничества</div>
        <h1 class="section-title mb-3">Паломнические маршруты</h1>
        <p class="section-lead mb-0">Однодневные, многодневные, тематические, семейные и молодёжные программы.</p>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-5" method="GET" action="{{ route('routes.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="q">Поиск маршрута</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название или описание">
                </div>
                <div class="col-md-5 col-lg-3">
                    <label class="form-label" for="category">Категория</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Все категории</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="difficulty">Сложность</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                        <option value="">Любая</option>
                        @foreach($difficulties as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['difficulty'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button class="btn btn-pm-gold" type="submit"><i class="bi bi-funnel me-1"></i>Найти</button>
                </div>
            </div>
        </form>

        <div class="row g-4">
            @forelse($routes as $pilgrimageRoute)
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm">
                        @if($pilgrimageRoute->cover_url)
                            <img class="object-cover" src="{{ $pilgrimageRoute->cover_url }}" alt="{{ $pilgrimageRoute->title }}">
                        @else
                            <div class="object-placeholder"><i class="bi bi-signpost-split"></i></div>
                        @endif
                        <div class="p-4">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge rounded-pill object-type-badge">{{ $categories[$pilgrimageRoute->category] ?? $pilgrimageRoute->category }}</span>
                                <span class="badge rounded-pill text-bg-light">{{ $difficulties[$pilgrimageRoute->difficulty] ?? $pilgrimageRoute->difficulty }}</span>
                            </div>
                            <h2 class="object-title mb-3">{{ $pilgrimageRoute->title }}</h2>
                            <div class="object-meta d-flex flex-wrap gap-3 mb-3">
                                <span><i class="bi bi-clock me-1"></i>{{ $pilgrimageRoute->duration_days }} дн.</span>
                                <span><i class="bi bi-geo-alt me-1"></i>{{ $pilgrimageRoute->objects_count }} точек</span>
                                @if($pilgrimageRoute->base_price !== null)<span><i class="bi bi-wallet2 me-1"></i>от {{ number_format((float)$pilgrimageRoute->base_price, 0, ',', ' ') }} ₽</span>@endif
                            </div>
                            @if($pilgrimageRoute->short_description)<p class="text-secondary small mb-4">{{ \Illuminate\Support\Str::limit($pilgrimageRoute->short_description, 160) }}</p>@endif
                            <a class="btn btn-pm-gold w-100" href="{{ route('routes.show', $pilgrimageRoute) }}">Открыть маршрут</a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="filter-card p-5 text-center">
                        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-signpost-split"></i></div>
                        <h2 class="h4 mb-3">Опубликованных маршрутов пока нет</h2>
                        <p class="text-secondary mb-4">Создайте маршрут в административной панели, добавьте точки и включите публикацию.</p>
                        @auth
                            @if(auth()->user()->isAdmin())<a class="btn btn-pm-green" href="{{ route('admin.modules.create', 'routes') }}">Создать маршрут</a>@endif
                        @endauth
                    </div>
                </div>
            @endforelse
        </div>

        @if($routes->hasPages())<div class="mt-5">{{ $routes->links() }}</div>@endif
    </div>
</section>
@endsection
