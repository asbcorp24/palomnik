@extends('site.layouts.app')

@section('title', 'Интерактивная карта — Московский паломник')

@section('content')
<div class="map-shell">
    <aside class="map-sidebar">
        <div class="section-kicker mb-2">Интерактивная карта</div>
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

        <div class="info-card p-3 mb-4">
            <div class="small fw-semibold mb-2"><i class="bi bi-layers me-2"></i>Слои карты</div>
            <div class="small text-secondary">На самой карте доступно переключение: схема, спутник и гибрид. Исторический слой XIX века подключается после подготовки лицензированного набора тайлов.</div>
        </div>

        <div class="small text-secondary mb-3">Объектов на карте: <strong class="text-dark">{{ $objects->count() }}</strong></div>
        <div class="d-grid gap-2">
            @forelse($objects as $object)
                <a class="map-object-row text-decoration-none" href="{{ $object['url'] }}">
                    <div class="d-flex gap-3 align-items-start">
                        @if($object['cover'])<img src="{{ $object['cover'] }}" alt="{{ $object['name'] }}" style="width:66px;height:58px;object-fit:cover;border-radius:12px">@else<span class="category-icon"><i class="bi bi-buildings"></i></span>@endif
                        <div class="min-w-0"><div class="small text-secondary mb-1">{{ $object['type'] ?: 'Паломнический объект' }}</div><div class="fw-semibold lh-sm mb-2">{{ $object['name'] }}</div><div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $object['address'] }}</div>@if(count($object['sanctities']))<div class="small text-secondary mt-1 text-truncate">{{ implode(', ', $object['sanctities']->all()) }}</div>@endif</div>
                    </div>
                </a>
            @empty
                <div class="map-object-row text-center py-4"><i class="bi bi-search display-5 text-secondary"></i><p class="small text-secondary mt-3 mb-0">По заданным фильтрам объекты не найдены.</p></div>
            @endforelse
        </div>
    </aside>

    <div id="pilgrim-map" class="map-canvas">
        @if(!config('palomnik.maps.yandex_key'))
            <div class="map-fallback"><div style="max-width:520px"><div class="object-placeholder rounded-circle mx-auto mb-4" style="width:115px;aspect-ratio:1"><i class="bi bi-map"></i></div><h2 class="h3 mb-3">Карта подготовлена к подключению</h2><p class="text-secondary mb-4">Добавьте ключ JavaScript API Яндекс Карт в переменную <code>YANDEX_MAPS_API_KEY</code> файла <code>.env</code>, затем выполните <code>php artisan config:clear</code>.</p><a class="btn btn-pm-green" href="{{ route('objects.index') }}">Открыть каталог</a></div></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
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
})();
</script>
@endpush

@if(config('palomnik.maps.yandex_key'))
    @push('scripts')
        <script src="https://api-maps.yandex.ru/2.1/?apikey={{ urlencode(config('palomnik.maps.yandex_key')) }}&lang=ru_RU"></script>
        <script>
            const pilgrimObjects = @json($objects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            function escapeHtml(value) { return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }

            ymaps.ready(function () {
                const map = new ymaps.Map('pilgrim-map', {
                    center: [55.751244, 37.618423],
                    zoom: 9,
                    controls: ['zoomControl', 'geolocationControl', 'fullscreenControl', 'typeSelector', 'routePanelControl']
                });

                const clusterer = new ymaps.Clusterer({ preset:'islands#invertedVioletClusterIcons', groupByCoordinates:false, clusterDisableClickZoom:false, clusterOpenBalloonOnClick:true });
                const placemarks = pilgrimObjects.map(function (object) {
                    const sanctities = Array.isArray(object.sanctities) && object.sanctities.length ? `<div style="font-size:12px;color:#8f6a20;margin-bottom:8px">${object.sanctities.map(escapeHtml).join(', ')}</div>` : '';
                    const content = `<div style="max-width:270px;font-family:Arial,sans-serif">${object.cover ? `<img src="${escapeHtml(object.cover)}" alt="" style="width:100%;height:125px;object-fit:cover;border-radius:10px;margin-bottom:10px">` : ''}<div style="font-size:12px;color:#746c64;margin-bottom:4px">${escapeHtml(object.type)}</div><div style="font-weight:700;font-size:16px;margin-bottom:7px">${escapeHtml(object.name)}</div>${sanctities}<div style="font-size:12px;color:#746c64;margin-bottom:10px">${escapeHtml(object.address)}</div><a href="${escapeHtml(object.url)}" style="color:#26443b;font-weight:700">Открыть карточку →</a></div>`;
                    return new ymaps.Placemark([object.latitude, object.longitude], { hintContent:escapeHtml(object.name), balloonContent:content }, { preset:'islands#circleIcon', iconColor:object.marker_color || '#b58a32' });
                });
                clusterer.add(placemarks); map.geoObjects.add(clusterer);
                if (placemarks.length > 0) map.setBounds(clusterer.getBounds(), {checkZoomRange:true, zoomMargin:45});
            });
        </script>
    @endpush
@endif
