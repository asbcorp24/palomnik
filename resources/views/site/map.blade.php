@extends('site.layouts.app')

@section('title', 'Интерактивная карта — Московский паломник')

@push('styles')
<link href="https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.css" rel="stylesheet">
<style>
    .maplibregl-popup-content { border-radius:18px; padding:0; overflow:hidden; box-shadow:0 18px 50px rgba(38,35,30,.2); }
    .map-popup { width:min(300px,75vw); font-family:Inter,Arial,sans-serif; }
    .map-popup img { width:100%; height:135px; object-fit:cover; }
    .map-popup-body { padding:16px; }
    .map-route-summary { position:absolute; z-index:4; left:16px; right:16px; bottom:18px; max-width:520px; margin:auto; }
    .map-layer-control { position:absolute; z-index:4; right:12px; top:12px; display:grid; gap:7px; }
    .map-layer-control .btn { background:rgba(255,253,249,.94); border-color:rgba(38,68,59,.22); box-shadow:0 5px 18px rgba(30,25,20,.12); }
    @media (max-width:991.98px) { .map-layer-control { top:68px; } }
</style>
@endpush

@section('content')
<div class="map-shell">
    <aside class="map-sidebar">
        <div class="section-kicker mb-2">MapLibre · OpenStreetMap</div>
        <h1 class="h2 mb-3">Святые места рядом</h1>
        <p class="text-secondary small mb-4">Поиск по названию, адресу, святыням, типу объекта, викариатству и благочинию.</p>

        <form class="mb-4" action="{{ route('map') }}" method="GET">
            <div class="mb-3">
                <div class="input-group"><span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span><input class="form-control border-start-0" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или святыня"></div>
            </div>
            <div class="row g-2">
                <div class="col-6"><select class="form-select form-select-sm" name="type"><option value="">Все типы</option>@foreach($types as $type)<option value="{{ $type->slug }}" @selected(($filters['type'] ?? '') === $type->slug)>{{ $type->name }}</option>@endforeach</select></div>
                <div class="col-6"><select class="form-select form-select-sm" id="mapVicariate" name="vicariate"><option value="">Все викариатства</option>@foreach($vicariates as $vicariate)<option value="{{ $vicariate->slug }}" @selected(($filters['vicariate'] ?? '') === $vicariate->slug)>{{ $vicariate->name }}</option>@endforeach</select></div>
                <div class="col-6"><select class="form-select form-select-sm" id="mapDeanery" name="deanery"><option value="">Все благочиния</option>@foreach($deaneries as $deanery)<option value="{{ $deanery->slug }}" data-vicariate="{{ optional($deanery->vicariate)->slug }}" @selected(($filters['deanery'] ?? '') === $deanery->slug)>{{ $deanery->name }}</option>@endforeach</select></div>
                <div class="col-6"><select class="form-select form-select-sm" name="sanctity"><option value="">Все святыни</option>@foreach($sanctities as $sanctity)<option value="{{ $sanctity->slug }}" @selected(($filters['sanctity'] ?? '') === $sanctity->slug)>{{ $sanctity->name }}</option>@endforeach</select></div>
            </div>
            <div class="d-flex gap-2 mt-3"><button class="btn btn-pm-gold flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button><a class="btn btn-light" href="{{ route('map') }}"><i class="bi bi-x-lg"></i></a></div>
        </form>

        <div class="info-card p-3 mb-3">
            <label class="small fw-semibold mb-2" for="routeMode"><i class="bi bi-signpost-2 me-2"></i>Маршрут от моего местоположения</label>
            <select class="form-select form-select-sm" id="routeMode">
                <option value="pedestrian">Пешком</option>
                <option value="auto">На автомобиле</option>
                <option value="bicycle">На велосипеде</option>
                <option value="bus">Автобус</option>
                <option value="multimodal">Общественный транспорт</option>
            </select>
            <div class="small text-secondary mt-2">Маршрут рассчитывается движком Valhalla по данным OpenStreetMap.</div>
        </div>

        <div class="info-card p-3 mb-4">
            <div class="small fw-semibold mb-2"><i class="bi bi-layers me-2"></i>Слои карты</div>
            <div class="small text-secondary">Основной слой использует единый стиль MapLibre. Спутниковый и исторический слои появятся после задания лицензированных URL тайлов в <code>.env</code>.</div>
        </div>

        <div class="small text-secondary mb-3">Объектов на карте: <strong class="text-dark">{{ $objects->count() }}</strong></div>
        <div class="d-grid gap-2">
            @forelse($objects as $object)
                <button class="map-object-row text-start" type="button" data-map-object="{{ $object['id'] }}">
                    <div class="d-flex gap-3 align-items-start">
                        @if($object['cover'])<img src="{{ $object['cover'] }}" alt="{{ $object['name'] }}" style="width:66px;height:58px;object-fit:cover;border-radius:12px">@else<span class="category-icon"><i class="bi bi-buildings"></i></span>@endif
                        <div class="min-w-0"><div class="small text-secondary mb-1">{{ $object['type'] ?: 'Паломнический объект' }}</div><div class="fw-semibold lh-sm mb-2">{{ $object['name'] }}</div><div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $object['address'] }}</div>@if(count($object['sanctities']))<div class="small text-secondary mt-1 text-truncate">{{ implode(', ', $object['sanctities']->all()) }}</div>@endif</div>
                    </div>
                </button>
            @empty
                <div class="map-object-row text-center py-4"><i class="bi bi-search display-5 text-secondary"></i><p class="small text-secondary mt-3 mb-0">По заданным фильтрам объекты не найдены.</p></div>
            @endforelse
        </div>
    </aside>

    <div id="pilgrim-map" class="map-canvas position-relative">
        <div class="map-layer-control" aria-label="Слои карты">
            <button class="btn btn-sm active" type="button" data-layer-mode="base"><i class="bi bi-map me-1"></i>Схема</button>
            @if(config('palomnik.maps.satellite_tiles'))<button class="btn btn-sm" type="button" data-layer-mode="satellite"><i class="bi bi-globe2 me-1"></i>Спутник</button>@endif
            @if(config('palomnik.maps.historic_tiles'))<button class="btn btn-sm" type="button" data-layer-mode="historic"><i class="bi bi-hourglass-split me-1"></i>История</button>@endif
        </div>
        <div id="mapRouteSummary" class="map-route-summary alert alert-light border shadow-sm d-none mb-0"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.js"></script>
<script>
(function () {
    const vicariate = document.getElementById('mapVicariate');
    const deanery = document.getElementById('mapDeanery');
    function filterDeaneries() {
        if (!vicariate || !deanery) return;
        Array.from(deanery.options).forEach((option, index) => {
            if (!index) return;
            const visible = !vicariate.value || option.dataset.vicariate === vicariate.value;
            option.hidden = !visible;
            if (!visible && option.selected) deanery.value = '';
        });
    }
    vicariate?.addEventListener('change', filterDeaneries);
    filterDeaneries();

    const objects = @json($objects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    const objectIndex = new Map(objects.map(item => [String(item.id), item]));
    const styleUrl = @json(config('palomnik.maps.style_url') ?: route('api.v1.map.style'));
    const routeUrl = @json(route('api.v1.map.route'));
    const satelliteUrl = @json(config('palomnik.maps.satellite_tiles'));
    const historicUrl = @json(config('palomnik.maps.historic_tiles'));
    const attribution = @json(config('palomnik.maps.attribution'));
    const summary = document.getElementById('mapRouteSummary');

    const geojson = {
        type: 'FeatureCollection',
        features: objects.filter(item => Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude))).map(item => ({
            type: 'Feature',
            geometry: {type: 'Point', coordinates: [Number(item.longitude), Number(item.latitude)]},
            properties: {
                id: String(item.id),
                name: item.name,
                type: item.type || 'Паломнический объект',
                address: item.address || '',
                cover: item.cover || '',
                url: item.url,
                marker_color: item.marker_color || '#b58a32',
                sanctities: Array.from(item.sanctities || []).join(', '),
            }
        }))
    };

    const map = new maplibregl.Map({
        container: 'pilgrim-map',
        style: styleUrl,
        center: [37.618423, 55.751244],
        zoom: 8.5,
        attributionControl: false,
    });

    map.addControl(new maplibregl.NavigationControl({visualizePitch:true}), 'bottom-right');
    map.addControl(new maplibregl.GeolocateControl({positionOptions:{enableHighAccuracy:true}, trackUserLocation:true, showUserHeading:true}), 'bottom-right');
    map.addControl(new maplibregl.FullscreenControl(), 'bottom-right');
    map.addControl(new maplibregl.AttributionControl({compact:true, customAttribution:attribution}), 'bottom-left');

    function escapeHtml(value) {
        return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    }

    function popupHtml(item) {
        const sanctities = item.sanctities ? `<div class="small mb-2" style="color:#8f6a20">${escapeHtml(item.sanctities)}</div>` : '';
        return `<article class="map-popup">${item.cover ? `<img src="${escapeHtml(item.cover)}" alt="">` : ''}<div class="map-popup-body"><div class="small text-secondary mb-1">${escapeHtml(item.type)}</div><div class="fw-bold mb-2">${escapeHtml(item.name)}</div>${sanctities}<div class="small text-secondary mb-3">${escapeHtml(item.address)}</div><div class="d-grid gap-2"><a class="btn btn-sm btn-pm-green" href="${escapeHtml(item.url)}">Открыть карточку</a><button class="btn btn-sm btn-outline-pm" type="button" data-route-object="${escapeHtml(item.id)}"><i class="bi bi-signpost-2 me-1"></i>Маршрут отсюда</button></div></div></article>`;
    }

    function showObject(item) {
        map.easeTo({center:[Number(item.longitude), Number(item.latitude)], zoom:14});
        new maplibregl.Popup({offset:22, maxWidth:'320px'})
            .setLngLat([Number(item.longitude), Number(item.latitude)])
            .setHTML(popupHtml({...item, sanctities:Array.from(item.sanctities || []).join(', ')}))
            .addTo(map);
    }

    map.on('load', () => {
        map.addSource('pilgrim-objects', {type:'geojson', data:geojson, cluster:true, clusterMaxZoom:14, clusterRadius:46});
        map.addLayer({id:'pilgrim-clusters', type:'circle', source:'pilgrim-objects', filter:['has','point_count'], paint:{'circle-color':'#26443b','circle-radius':['step',['get','point_count'],18,10,23,40,29],'circle-stroke-width':3,'circle-stroke-color':'#fffdf9'}});
        map.addLayer({id:'pilgrim-cluster-count', type:'symbol', source:'pilgrim-objects', filter:['has','point_count'], layout:{'text-field':['get','point_count_abbreviated'],'text-size':12}, paint:{'text-color':'#ffffff'}});
        map.addLayer({id:'pilgrim-points', type:'circle', source:'pilgrim-objects', filter:['!', ['has','point_count']], paint:{'circle-color':['coalesce',['get','marker_color'],'#b58a32'],'circle-radius':9,'circle-stroke-width':3,'circle-stroke-color':'#fffdf9'}});

        if (satelliteUrl) {
            map.addSource('satellite', {type:'raster', tiles:[satelliteUrl], tileSize:256, attribution});
            map.addLayer({id:'satellite', type:'raster', source:'satellite', layout:{visibility:'none'}});
        }
        if (historicUrl) {
            map.addSource('historic', {type:'raster', tiles:[historicUrl], tileSize:256, attribution});
            map.addLayer({id:'historic', type:'raster', source:'historic', layout:{visibility:'none'}, paint:{'raster-opacity':0.88}});
        }

        if (geojson.features.length > 1) {
            const bounds = geojson.features.reduce((bounds, feature) => bounds.extend(feature.geometry.coordinates), new maplibregl.LngLatBounds(geojson.features[0].geometry.coordinates, geojson.features[0].geometry.coordinates));
            map.fitBounds(bounds, {padding:60, maxZoom:13});
        }
    });

    map.on('click', 'pilgrim-clusters', async event => {
        const feature = map.queryRenderedFeatures(event.point, {layers:['pilgrim-clusters']})[0];
        if (!feature) return;
        const zoom = await map.getSource('pilgrim-objects').getClusterExpansionZoom(feature.properties.cluster_id);
        map.easeTo({center:feature.geometry.coordinates, zoom});
    });

    map.on('click', 'pilgrim-points', event => {
        const feature = event.features?.[0];
        if (!feature) return;
        const item = objectIndex.get(String(feature.properties.id));
        if (item) showObject(item);
    });

    ['pilgrim-clusters','pilgrim-points'].forEach(layer => {
        map.on('mouseenter', layer, () => map.getCanvas().style.cursor = 'pointer');
        map.on('mouseleave', layer, () => map.getCanvas().style.cursor = '');
    });

    document.querySelectorAll('[data-map-object]').forEach(button => button.addEventListener('click', () => {
        const item = objectIndex.get(button.dataset.mapObject);
        if (item) showObject(item);
    }));

    document.querySelectorAll('[data-layer-mode]').forEach(button => button.addEventListener('click', () => {
        const mode = button.dataset.layerMode;
        ['satellite','historic'].forEach(id => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', mode === id ? 'visible' : 'none'); });
        document.querySelectorAll('[data-layer-mode]').forEach(item => item.classList.toggle('active', item === button));
    }));

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-route-object]');
        if (!button) return;
        const item = objectIndex.get(String(button.dataset.routeObject));
        if (!item) return;
        buildRoute(item);
    });

    async function buildRoute(item) {
        summary.className = 'map-route-summary alert alert-light border shadow-sm mb-0';
        summary.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>Определяем местоположение и строим маршрут…</div>';
        try {
            const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {enableHighAccuracy:true, timeout:12000}));
            const response = await fetch(routeUrl, {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json'},
                body:JSON.stringify({
                    mode:document.getElementById('routeMode').value,
                    locations:[
                        {latitude:position.coords.latitude, longitude:position.coords.longitude},
                        {latitude:Number(item.latitude), longitude:Number(item.longitude)}
                    ]
                })
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Маршрут не найден');
            const route = payload.data;
            const geometry = {type:'Feature', properties:{}, geometry:route.geometry};
            if (map.getSource('active-route')) map.getSource('active-route').setData(geometry);
            else {
                map.addSource('active-route', {type:'geojson', data:geometry});
                map.addLayer({id:'active-route-outline',type:'line',source:'active-route',paint:{'line-color':'#fffdf9','line-width':8,'line-opacity':.9}});
                map.addLayer({id:'active-route',type:'line',source:'active-route',paint:{'line-color':'#b58a32','line-width':5}});
            }
            const bounds = route.geometry.coordinates.reduce((bounds, coordinate) => bounds.extend(coordinate), new maplibregl.LngLatBounds(route.geometry.coordinates[0], route.geometry.coordinates[0]));
            map.fitBounds(bounds, {padding:{top:70,bottom:100,left:70,right:70}, maxZoom:16});
            const km = (route.distance_meters / 1000).toFixed(1);
            const minutes = Math.max(1, Math.round(route.duration_seconds / 60));
            summary.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-3"><div><strong>${escapeHtml(item.name)}</strong><div class="small text-secondary mt-1">${km} км · примерно ${minutes} мин.</div></div><button class="btn-close" type="button" aria-label="Закрыть"></button></div>`;
            summary.querySelector('.btn-close').addEventListener('click', clearRoute);
        } catch (error) {
            summary.className = 'map-route-summary alert alert-danger shadow-sm mb-0';
            summary.textContent = error.message || 'Не удалось построить маршрут.';
        }
    }

    function clearRoute() {
        if (map.getLayer('active-route')) map.removeLayer('active-route');
        if (map.getLayer('active-route-outline')) map.removeLayer('active-route-outline');
        if (map.getSource('active-route')) map.removeSource('active-route');
        summary.classList.add('d-none');
    }
})();
</script>
@endpush
