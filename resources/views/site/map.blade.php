@extends('site.layouts.app')

@section('title', 'Интерактивная карта — Московский паломник')

@section('content')
<div class="map-shell">
    <aside class="map-sidebar">
        <div class="section-kicker mb-2">Интерактивная карта</div>
        <h1 class="h2 mb-3">Святые места рядом</h1>
        <p class="text-secondary small mb-4">На карте отображаются опубликованные объекты с координатами из административной панели.</p>

        <form class="mb-4" action="{{ route('objects.index') }}" method="GET">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input class="form-control border-start-0" name="q" placeholder="Поиск по каталогу">
                <button class="btn btn-pm-gold" type="submit">Найти</button>
            </div>
        </form>

        <div class="small text-secondary mb-3">Объектов на карте: <strong class="text-dark">{{ $objects->count() }}</strong></div>
        <div class="d-grid gap-2">
            @forelse($objects as $object)
                <a class="map-object-row text-decoration-none" href="{{ $object['url'] }}">
                    <div class="d-flex gap-3 align-items-start">
                        @if($object['cover'])
                            <img src="{{ $object['cover'] }}" alt="{{ $object['name'] }}" style="width:66px;height:58px;object-fit:cover;border-radius:12px">
                        @else
                            <span class="category-icon"><i class="bi bi-buildings"></i></span>
                        @endif
                        <div class="min-w-0">
                            <div class="small text-secondary mb-1">{{ $object['type'] ?: 'Паломнический объект' }}</div>
                            <div class="fw-semibold lh-sm mb-2">{{ $object['name'] }}</div>
                            <div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $object['address'] }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="map-object-row text-center py-4">
                    <i class="bi bi-map display-5 text-secondary"></i>
                    <p class="small text-secondary mt-3 mb-0">Добавьте и опубликуйте объекты в административной панели.</p>
                </div>
            @endforelse
        </div>
    </aside>

    <div id="pilgrim-map" class="map-canvas">
        @if(!config('palomnik.maps.yandex_key'))
            <div class="map-fallback">
                <div style="max-width:520px">
                    <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:115px;aspect-ratio:1"><i class="bi bi-map"></i></div>
                    <h2 class="h3 mb-3">Карта подготовлена к подключению</h2>
                    <p class="text-secondary mb-4">Добавьте ключ JavaScript API Яндекс Карт в переменную <code>YANDEX_MAPS_API_KEY</code> файла <code>.env</code>, затем выполните <code>php artisan config:clear</code>.</p>
                    <a class="btn btn-pm-green" href="{{ route('objects.index') }}">Открыть каталог</a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@if(config('palomnik.maps.yandex_key'))
    @push('scripts')
        <script src="https://api-maps.yandex.ru/2.1/?apikey={{ urlencode(config('palomnik.maps.yandex_key')) }}&lang=ru_RU"></script>
        <script>
            const pilgrimObjects = @json($objects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            ymaps.ready(function () {
                const map = new ymaps.Map('pilgrim-map', {
                    center: [55.751244, 37.618423],
                    zoom: 9,
                    controls: ['zoomControl', 'geolocationControl', 'fullscreenControl']
                });

                const clusterer = new ymaps.Clusterer({
                    preset: 'islands#invertedGoldClusterIcons',
                    groupByCoordinates: false,
                    clusterDisableClickZoom: false,
                    clusterOpenBalloonOnClick: true
                });

                const placemarks = pilgrimObjects.map(function (object) {
                    const content = `
                        <div style="max-width:260px;font-family:Arial,sans-serif">
                            ${object.cover ? `<img src="${escapeHtml(object.cover)}" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:10px;margin-bottom:10px">` : ''}
                            <div style="font-size:12px;color:#746c64;margin-bottom:4px">${escapeHtml(object.type)}</div>
                            <div style="font-weight:700;font-size:16px;margin-bottom:7px">${escapeHtml(object.name)}</div>
                            <div style="font-size:12px;color:#746c64;margin-bottom:10px">${escapeHtml(object.address)}</div>
                            <a href="${escapeHtml(object.url)}" style="color:#26443b;font-weight:700">Открыть карточку →</a>
                        </div>`;

                    return new ymaps.Placemark(
                        [object.latitude, object.longitude],
                        {
                            hintContent: escapeHtml(object.name),
                            balloonContent: content
                        },
                        {
                            preset: 'islands#goldIcon'
                        }
                    );
                });

                clusterer.add(placemarks);
                map.geoObjects.add(clusterer);

                if (placemarks.length > 0) {
                    map.setBounds(clusterer.getBounds(), {
                        checkZoomRange: true,
                        zoomMargin: 45
                    });
                }
            });
        </script>
    @endpush
@endif
