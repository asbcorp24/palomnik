@extends('site.layouts.app')

@section('title', 'Интерактивная карта — Московский паломник')

@push('styles')
<link href="{{ asset('assets/vendor/maplibre/maplibre-gl.css') }}" rel="stylesheet">
<style>
    .maplibregl-popup-content { border-radius:18px; padding:0; overflow:hidden; box-shadow:0 18px 50px rgba(38,35,30,.2); }
    .map-popup { width:min(320px,78vw); font-family:Inter,Arial,sans-serif; }
    .map-popup img { width:100%; height:145px; object-fit:cover; }
    .map-popup-body { padding:16px; }
    .map-route-summary { position:absolute; z-index:4; left:16px; right:16px; bottom:18px; max-width:620px; margin:auto; }
    .map-layer-control { position:absolute; z-index:4; right:12px; top:12px; display:grid; gap:7px; }
    .map-layer-control .btn { background:rgba(255,253,249,.94); border-color:rgba(38,68,59,.22); box-shadow:0 5px 18px rgba(30,25,20,.12); }
    .map-loading-status { position:absolute; z-index:4; left:16px; top:16px; padding:8px 12px; border-radius:999px; background:rgba(255,253,249,.94); box-shadow:0 5px 18px rgba(30,25,20,.12); font-size:.8rem; }
    .route-stop-marker { width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:50% 50% 50% 12%; transform:rotate(-45deg); background:#26443b; color:#fff; border:3px solid #fffdf9; box-shadow:0 6px 18px rgba(38,68,59,.35); cursor:pointer; font-size:12px; font-weight:800; }
    .route-stop-marker span { transform:rotate(45deg); }
    .poi-filter-row { display:flex; align-items:center; gap:9px; padding:5px 0; }
    .poi-filter-dot { width:11px; height:11px; border-radius:50%; box-shadow:0 0 0 2px #fff,0 0 0 3px rgba(38,35,30,.12); }
    .map-object-list { min-height:130px; }
    .map-object-list .map-object-row { width:100%; }
    @media (max-width:991.98px) {
        .map-layer-control { top:68px; }
        .map-loading-status { top:16px; left:12px; }
    }
</style>
@endpush

@section('content')
<div class="map-shell">
    <aside class="map-sidebar">
        <div class="section-kicker mb-2">MapLibre · OpenStreetMap</div>
        <h1 class="h2 mb-3">Храмы и монастыри</h1>
        <p class="text-secondary small mb-4">Карта загружает только объекты в видимой области. Приближайте карту, чтобы увидеть отдельные храмы и точки интереса.</p>

        <form class="mb-4" action="{{ route('map') }}" method="GET">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input class="form-control border-start-0" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или святыня">
                </div>
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <select class="form-select form-select-sm" name="type">
                        <option value="">Все типы</option>
                        @foreach($types as $type)
                            <option value="{{ $type->slug }}" @selected(($filters['type'] ?? '') === $type->slug)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6">
                    <select class="form-select form-select-sm" id="mapVicariate" name="vicariate">
                        <option value="">Все викариатства</option>
                        @foreach($vicariates as $vicariate)
                            <option value="{{ $vicariate->slug }}" @selected(($filters['vicariate'] ?? '') === $vicariate->slug)>{{ $vicariate->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6">
                    <select class="form-select form-select-sm" id="mapDeanery" name="deanery">
                        <option value="">Все благочиния</option>
                        @foreach($deaneries as $deanery)
                            <option value="{{ $deanery->slug }}" data-vicariate="{{ optional($deanery->vicariate)->slug }}" @selected(($filters['deanery'] ?? '') === $deanery->slug)>{{ $deanery->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6">
                    <select class="form-select form-select-sm" name="sanctity">
                        <option value="">Все святыни</option>
                        @foreach($sanctities as $sanctity)
                            <option value="{{ $sanctity->slug }}" @selected(($filters['sanctity'] ?? '') === $sanctity->slug)>{{ $sanctity->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <select class="form-select form-select-sm" name="route">
                        <option value="">Без выбранного маршрута</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->slug }}" @selected(($filters['route'] ?? '') === $route->slug)>
                                {{ $route->title }} · {{ $route->objects_count }} точек
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-pm-gold flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button>
                <a class="btn btn-light" href="{{ route('map') }}"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>

        @if($selectedRoute)
            <div class="info-card p-3 mb-3">
                <div class="small text-secondary mb-1">Выбранный маршрут</div>
                <div class="fw-semibold mb-2">{{ $selectedRoute['title'] }}</div>
                <div class="small text-secondary mb-3">Точек пути: {{ count($selectedRoute['points']) }}</div>
                <a class="btn btn-sm btn-outline-pm w-100" href="{{ $selectedRoute['url'] }}">Открыть описание маршрута</a>
            </div>
        @endif

        <div class="info-card p-3 mb-3">
            <label class="small fw-semibold mb-2" for="routeMode">
                <i class="bi bi-signpost-2 me-2"></i>{{ $selectedRoute ? 'Способ прохождения выбранного маршрута' : 'Маршрут от моего местоположения' }}
            </label>
            <select class="form-select form-select-sm" id="routeMode">
                <option value="pedestrian">Пешком</option>
                <option value="auto">На автомобиле</option>
                <option value="bicycle">На велосипеде</option>
                <option value="bus">Автобус</option>
                <option value="multimodal">Общественный транспорт</option>
            </select>
            <div class="small text-secondary mt-2">Маршрут рассчитывается движком Valhalla по данным OpenStreetMap.</div>
        </div>

        <div class="info-card p-3 mb-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-pin-map-fill me-2"></i>Точки интереса рядом</div>
            @foreach($poiCategories as $key => $category)
                <label class="poi-filter-row small">
                    <input class="form-check-input mt-0" type="checkbox" data-poi-category value="{{ $key }}" checked>
                    <span class="poi-filter-dot" style="background:{{ $category['color'] }}"></span>
                    <i class="bi {{ $category['icon'] }}"></i>
                    <span>{{ $category['label'] }}</span>
                </label>
            @endforeach
            <div class="small text-secondary mt-2">В текущей области: <strong id="mapPoiCount">загрузка…</strong></div>
            <div class="small text-secondary">Точки интереса загружаются только после достаточного приближения карты.</div>
        </div>

        <div class="info-card p-3 mb-4">
            <div class="small fw-semibold mb-2"><i class="bi bi-layers me-2"></i>Слои карты</div>
            <div class="small text-secondary">Основной слой использует единый стиль MapLibre. Спутниковый и исторический слои появятся после задания лицензированных URL тайлов в <code>.env</code>.</div>
        </div>

        <div class="d-flex justify-content-between align-items-center small text-secondary mb-3">
            <span>Объектов в области: <strong id="mapObjectCount" class="text-dark">загрузка…</strong></span>
            <span><i class="bi bi-arrows-move me-1"></i>Перемещайте карту</span>
        </div>
        <div id="mapObjectList" class="map-object-list d-grid gap-2" aria-live="polite">
            <div class="map-object-row text-center py-4">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
                <p class="small text-secondary mt-3 mb-0">Загружаем объекты текущей области…</p>
            </div>
        </div>
    </aside>

    <div id="pilgrim-map" class="map-canvas position-relative">
        <div id="mapLoadingStatus" class="map-loading-status d-none">
            <span class="spinner-border spinner-border-sm me-1"></span>Обновляем область…
        </div>
        <div class="map-layer-control" aria-label="Слои карты">
            <button class="btn btn-sm active" type="button" data-layer-mode="base"><i class="bi bi-map me-1"></i>Схема</button>
            @if(config('palomnik.maps.satellite_tiles'))
                <button class="btn btn-sm" type="button" data-layer-mode="satellite"><i class="bi bi-globe2 me-1"></i>Спутник</button>
            @endif
            @if(config('palomnik.maps.historic_tiles'))
                <button class="btn btn-sm" type="button" data-layer-mode="historic"><i class="bi bi-hourglass-split me-1"></i>История</button>
            @endif
        </div>
        <div id="mapRouteSummary" class="map-route-summary alert alert-light border shadow-sm d-none mb-0"></div>
    </div>
</div>
@endsection

@php
    $pilgrimMapConfig = [
        'styleUrl' => config('palomnik.maps.style_url') ?: route('api.v1.map.style'),
        'objectsUrl' => route('api.v1.map.objects'),
        'objectDetailUrl' => url('/api/v1/map/objects/__ID__'),
        'pointsOfInterestUrl' => route('api.v1.map.points-of-interest'),
        'pointOfInterestDetailUrl' => url('/api/v1/map/points-of-interest/__ID__'),
        'routeUrl' => route('api.v1.map.route'),
        'satelliteUrl' => config('palomnik.maps.satellite_tiles'),
        'historicUrl' => config('palomnik.maps.historic_tiles'),
        'attribution' => config('palomnik.maps.attribution'),
        'filters' => [
            'q' => $filters['q'] ?? null,
            'type' => $filters['type'] ?? null,
            'vicariate' => $filters['vicariate'] ?? null,
            'deanery' => $filters['deanery'] ?? null,
            'sanctity' => $filters['sanctity'] ?? null,
        ],
        'selectedRoute' => $selectedRoute,
        'focusedPointOfInterestId' => $focusedPointOfInterestId,
    ];
@endphp

@push('scripts')
<script src="{{ asset('assets/vendor/maplibre/maplibre-gl.js') }}"></script>
<script>
window.pilgrimMapConfig = {!! json_encode(
    $pilgrimMapConfig,
    JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT
) !!};
</script>
<script src="{{ asset('js/map-viewport.js') }}"></script>
@endpush
