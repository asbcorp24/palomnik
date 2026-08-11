@extends('site.layouts.app')

@section('title', 'Храмы и монастыри — Московский паломник')

@push('styles')
<link href="{{ asset('assets/vendor/maplibre/maplibre-gl.css') }}" rel="stylesheet">
<style>
    .object-catalog-map-card { overflow:hidden; padding:0; }
    .object-catalog-mini-map { position:relative; width:100%; height:340px; background:#ebe7df; }
    .object-catalog-mini-map .maplibregl-canvas { outline:none; }
    .object-catalog-map-marker {
        width:38px;
        height:38px;
        display:flex;
        align-items:center;
        justify-content:center;
        border:3px solid #fffdf9;
        border-radius:50% 50% 50% 12%;
        color:#fff;
        font-size:17px;
        box-shadow:0 8px 22px rgba(24,35,31,.3);
        transform:rotate(-45deg);
        background:var(--pm-gold);
        cursor:pointer;
    }
    .object-catalog-map-marker i { transform:rotate(45deg); }
    .object-catalog-map-empty {
        min-height:340px;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:30px;
        text-align:center;
        color:var(--pm-muted);
    }
    .object-catalog-map-popup { min-width:210px; max-width:270px; }
    .object-catalog-map-popup a { color:var(--pm-green); font-weight:700; text-decoration:none; }
    @media (max-width:767.98px) {
        .object-catalog-mini-map { height:285px; }
        .object-catalog-map-empty { min-height:285px; }
    }
</style>
@endpush

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-3">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item active">Храмы и монастыри</li>
            </ol>
        </nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Единый реестр</div>
                <h1 class="section-title mb-3">Храмы и монастыри</h1>
                <p class="section-lead mb-0">Ищите объекты по названию, адресу, типу, викариатству и благочинию. Регистр букв и небольшие опечатки не влияют на результат.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a class="btn btn-pm-green" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Показать на карте</a>
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-5" method="GET" action="{{ route('objects.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="q">Поиск</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или святыня — можно с опечаткой">
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="type">Тип</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Все типы</option>
                        @foreach($types as $type)
                            <option value="{{ $type->slug }}" @selected(($filters['type'] ?? '') === $type->slug)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="vicariate">Викариатство</label>
                    <select class="form-select" id="vicariate" name="vicariate">
                        <option value="">Все</option>
                        @foreach($vicariates as $vicariate)
                            <option value="{{ $vicariate->slug }}" @selected(($filters['vicariate'] ?? '') === $vicariate->slug)>{{ $vicariate->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="deanery">Благочиние</label>
                    <select class="form-select" id="deanery" name="deanery">
                        <option value="">Все</option>
                        @foreach($deaneries as $deanery)
                            <option value="{{ $deanery->slug }}" data-vicariate="{{ optional($deanery->vicariate)->slug }}" @selected(($filters['deanery'] ?? '') === $deanery->slug)>{{ $deanery->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="sort">Сортировка</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="none" @selected(($filters['sort'] ?? 'none') === 'none')>Без сортировки</option>
                        <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Популярные</option>
                        <option value="reviews" @selected(($filters['sort'] ?? '') === 'reviews')>С отзывами</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-pm-gold px-4" type="submit"><i class="bi bi-funnel me-2"></i>Применить</button>
                    <a class="btn btn-light px-4" href="{{ route('objects.index') }}">Сбросить</a>
                </div>
            </div>
        </form>

        @php
            $catalogMapObjects = collect($objects->items())
                ->filter(fn ($object) => $object->latitude !== null && $object->longitude !== null)
                ->map(fn ($object) => [
                    'name' => $object->name,
                    'address' => $object->address,
                    'type' => optional($object->objectType)->name ?: 'Паломнический объект',
                    'latitude' => (float) $object->latitude,
                    'longitude' => (float) $object->longitude,
                    'url' => route('objects.show', $object),
                ])
                ->values();
            $catalogMapQuery = collect([
                'q' => $filters['q'] ?? null,
                'type' => $filters['type'] ?? null,
                'vicariate' => $filters['vicariate'] ?? null,
                'deanery' => $filters['deanery'] ?? null,
            ])->filter(fn ($value) => filled($value))->all();
        @endphp

        <section class="info-card object-catalog-map-card mb-5" aria-labelledby="objectCatalogMapTitle">
            <div class="p-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom">
                <div>
                    <div class="section-kicker mb-1">На карте</div>
                    <h2 class="h4 mb-1" id="objectCatalogMapTitle">Объекты этой страницы</h2>
                    <div class="small text-secondary">Показаны храмы и монастыри из текущей выборки, у которых указаны координаты.</div>
                </div>
                <a class="btn btn-outline-pm" href="{{ route('map', $catalogMapQuery) }}">
                    <i class="bi bi-arrows-fullscreen me-2"></i>Открыть большую карту
                </a>
            </div>
            <div
                id="objectCatalogMiniMap"
                class="object-catalog-mini-map"
                data-style-url="{{ url('/api/v1/map/style.json') }}"
                aria-label="Карта объектов текущей страницы"
            ></div>
            <script type="application/json" id="objectCatalogMiniMapData">@json($catalogMapObjects)</script>
        </section>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="text-secondary">Найдено объектов: <strong class="text-dark">{{ $objects->total() }}</strong></div>
        </div>

        <div class="row g-4">
            @forelse($objects as $object)
                <div class="col-md-6 col-xl-4">@include('site.partials.object-card', ['object' => $object])</div>
            @empty
                <div class="col-12">
                    <div class="filter-card text-center py-5">
                        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-search"></i></div>
                        <h2 class="h4 mb-3">Объекты не найдены</h2>
                        <p class="text-secondary mb-4">Измените параметры поиска или добавьте новые объекты через административную панель.</p>
                        <a class="btn btn-outline-pm" href="{{ route('objects.index') }}">Очистить фильтры</a>
                    </div>
                </div>
            @endforelse
        </div>

        @if($objects->hasPages())
            <div class="mt-5">{{ $objects->links() }}</div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/maplibre/maplibre-gl.js') }}"></script>
<script src="{{ asset('js/object-catalog-mini-map.js') }}?v=1"></script>
<script>
(function () {
    const vicariate = document.getElementById('vicariate');
    const deanery = document.getElementById('deanery');
    if (!vicariate || !deanery) return;

    function filterDeaneries() {
        const selected = vicariate.value;
        Array.from(deanery.options).forEach(function (option, index) {
            if (index === 0) return;
            const visible = !selected || option.dataset.vicariate === selected;
            option.hidden = !visible;
            if (!visible && option.selected) deanery.value = '';
        });
    }

    vicariate.addEventListener('change', filterDeaneries);
    filterDeaneries();
})();
</script>
@endpush
