@extends('site.layouts.app')

@section('title', 'Маршрут дня — Московский паломник')
@section('meta_description', 'Составьте паломнический маршрут по храмам и монастырям с учётом местоположения, времени, расстояния, способа передвижения и расписаний.')

@push('styles')
<link href="{{ asset('assets/vendor/maplibre/maplibre-gl.css') }}" rel="stylesheet">
<style>
    .day-route-map { min-height:430px;border-radius:22px;overflow:hidden;border:1px solid rgba(111,77,55,.14);background:#eee7da; }
    .day-route-summary { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px; }
    .day-route-summary-item { padding:18px;border-radius:16px;background:var(--pm-paper,#fffdf9);border:1px solid rgba(111,77,55,.13); }
    .day-route-summary-value { font-size:1.45rem;font-weight:800;line-height:1.1; }
    .day-route-step { position:relative;padding:22px 22px 22px 76px;border-radius:20px;background:#fffdf9;border:1px solid rgba(111,77,55,.14); }
    .day-route-step-number { position:absolute;left:20px;top:20px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#26443b;color:#fff;font-weight:800; }
    .schedule-open { background:rgba(25,135,84,.12);color:#146c43; }
    .schedule-unknown { background:rgba(255,193,7,.18);color:#7a5a00; }
    .schedule-closed { background:rgba(220,53,69,.12);color:#b02a37; }
    .day-route-marker { width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;color:#fff;border:3px solid #fff;box-shadow:0 5px 16px rgba(0,0,0,.25);font-weight:800; }
    .day-route-start-marker { width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#b08a3e;color:#fff;border:3px solid #fff;box-shadow:0 5px 16px rgba(0,0,0,.25); }
    .criteria-help { font-size:.82rem;color:#746c64; }
    @media (max-width:767.98px) { .day-route-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }.day-route-map{min-height:350px;} }
</style>
@endpush

@section('content')
@php
    $durationText = static function (int $minutes): string {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        if ($hours === 0) return $rest.' мин.';
        return $hours.' ч.'.($rest ? ' '.$rest.' мин.' : '');
    };
    $startValue = old('start_at', $form['start_at'] ?? now()->addMinutes(30)->format('Y-m-d\TH:i'));
    try {
        $startValue = \Illuminate\Support\Carbon::parse($startValue)->format('Y-m-d\TH:i');
    } catch (\Throwable $exception) {
        $startValue = now()->addMinutes(30)->format('Y-m-d\TH:i');
    }
@endphp

<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-3">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item"><a href="{{ route('routes.index') }}">Маршруты</a></li>
                <li class="breadcrumb-item active">Маршрут дня</li>
            </ol>
        </nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Персональное планирование</div>
                <h1 class="section-title mb-3">Маршрут дня</h1>
                <p class="section-lead mb-0">Укажите точку старта, свободное время и интересы. Система подберёт храмы и монастыри, проверит доступные часы и рассчитает последовательность посещения.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @auth
                    <a class="btn btn-outline-pm" href="{{ route('route-plans.index') }}"><i class="bi bi-bookmark me-2"></i>Мои маршруты</a>
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-5" method="POST" action="{{ route('day-route.generate') }}" id="dayRouteForm">
            @csrf
            <div class="row g-4">
                <div class="col-xl-5">
                    <h2 class="h4 mb-4">Условия маршрута</h2>

                    <div class="mb-3">
                        <label class="form-label" for="location_label">Где вы начинаете</label>
                        <input class="form-control" id="location_label" name="location_label" value="{{ old('location_label', $form['location_label'] ?? '') }}" maxlength="255" placeholder="Например, метро Таганская">
                        <div class="criteria-help mt-1">Введите понятный ориентир и поставьте точку на карте.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="latitude">Широта</label>
                            <input class="form-control" id="latitude" name="latitude" value="{{ old('latitude', $form['latitude'] ?? 55.751244) }}" readonly required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="longitude">Долгота</label>
                            <input class="form-control" id="longitude" name="longitude" value="{{ old('longitude', $form['longitude'] ?? 37.618423) }}" readonly required>
                        </div>
                    </div>

                    <button class="btn btn-outline-pm w-100 mb-4" id="locateMeButton" type="button"><i class="bi bi-crosshair me-2"></i>Использовать моё местоположение</button>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="start_at">Дата и время начала</label>
                            <input class="form-control" id="start_at" type="datetime-local" name="start_at" value="{{ $startValue }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="available_minutes">Свободное время</label>
                            <select class="form-select" id="available_minutes" name="available_minutes">
                                @foreach([90 => '1 ч. 30 мин.',120 => '2 часа',180 => '3 часа',240 => '4 часа',360 => '6 часов',480 => '8 часов'] as $value => $label)
                                    <option value="{{ $value }}" @selected((int) old('available_minutes', $form['available_minutes'] ?? 180) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transport_mode">Способ передвижения</label>
                            <select class="form-select" id="transport_mode" name="transport_mode">
                                @foreach($transportModes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('transport_mode', $form['transport_mode'] ?? 'walk') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="object_count">Количество объектов</label>
                            <select class="form-select" id="object_count" name="object_count">
                                @foreach(range(2, 8) as $value)
                                    <option value="{{ $value }}" @selected((int) old('object_count', $form['object_count'] ?? 3) === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="max_distance_km">Максимальная длина пути</label>
                            <div class="input-group"><input class="form-control" id="max_distance_km" type="number" min="1" max="120" step="1" name="max_distance_km" value="{{ old('max_distance_km', $form['max_distance_km'] ?? 8) }}"><span class="input-group-text">км</span></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="theme">Интересующая тема</label>
                            <select class="form-select" id="theme" name="theme">
                                @foreach($themes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('theme', $form['theme'] ?? 'any') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch border rounded-4 p-3 ps-5 mt-4">
                        <input type="hidden" name="allow_unknown_schedule" value="0">
                        <input class="form-check-input" id="allow_unknown_schedule" type="checkbox" name="allow_unknown_schedule" value="1" @checked((bool) old('allow_unknown_schedule', $form['allow_unknown_schedule'] ?? true))>
                        <label class="form-check-label fw-semibold" for="allow_unknown_schedule">Разрешить объекты с неразбираемым расписанием</label>
                        <div class="criteria-help mt-1">Они попадут в маршрут с предупреждением. Объекты, которые явно закрыты в выбранное время, исключаются.</div>
                    </div>

                    <button class="btn btn-pm-gold btn-lg w-100 mt-4" type="submit"><i class="bi bi-stars me-2"></i>Составить маршрут дня</button>
                </div>

                <div class="col-xl-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0" for="dayRouteMap">Точка начала</label>
                        <span class="criteria-help">Нажмите на карту или перетащите золотой маркер</span>
                    </div>
                    <div class="day-route-map" id="dayRouteMap"></div>
                </div>
            </div>
        </form>

        @if($result)
            <section id="dayRouteResult">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <div class="section-kicker mb-2">Готовое предложение</div>
                        <h2 class="section-title mb-2">{{ $result['title'] }}</h2>
                        <p class="section-lead mb-0">Начало {{ \Illuminate\Support\Carbon::parse($result['start']['starts_at'])->format('d.m.Y в H:i') }} · {{ $result['start']['label'] }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-pm" href="{{ $result['yandex_url'] }}" target="_blank" rel="noopener"><i class="bi bi-map me-2"></i>Открыть в Яндекс Картах</a>
                        @auth
                            <button class="btn btn-pm-green" type="button" data-bs-toggle="modal" data-bs-target="#saveDayRouteModal"><i class="bi bi-bookmark-plus me-2"></i>Сохранить</button>
                        @else
                            <a class="btn btn-pm-green" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Войти и сохранить</a>
                        @endauth
                    </div>
                </div>

                <div class="day-route-summary mb-4">
                    <div class="day-route-summary-item"><div class="day-route-summary-value">{{ $durationText((int)$result['summary']['total_minutes']) }}</div><div class="small text-secondary mt-2">общая продолжительность</div></div>
                    <div class="day-route-summary-item"><div class="day-route-summary-value">{{ number_format((float)$result['summary']['distance_km'], 1, ',', ' ') }} км</div><div class="small text-secondary mt-2">длина пути</div></div>
                    <div class="day-route-summary-item"><div class="day-route-summary-value">{{ $result['summary']['objects_count'] }}</div><div class="small text-secondary mt-2">храмов и монастырей</div></div>
                    <div class="day-route-summary-item"><div class="day-route-summary-value">{{ $durationText((int)$result['summary']['visit_minutes']) }}</div><div class="small text-secondary mt-2">на посещение объектов</div></div>
                </div>

                @if($result['warnings'])
                    <div class="alert alert-warning border-0 rounded-4 mb-4">
                        <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Перед поездкой</div>
                        <ul class="mb-0 small">@foreach($result['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-xl-5 order-xl-2">
                        <div class="day-route-map position-sticky" id="dayRouteResultMap" style="top:105px"></div>
                    </div>
                    <div class="col-xl-7 order-xl-1">
                        <div class="d-grid gap-3">
                            @foreach($result['stops'] as $index => $stop)
                                @php
                                    $scheduleClass = $stop['schedule_status'] === 'open' ? 'schedule-open' : ($stop['schedule_status'] === 'closed' ? 'schedule-closed' : 'schedule-unknown');
                                    $arrival = \Illuminate\Support\Carbon::parse($stop['arrival_at']);
                                    $departure = \Illuminate\Support\Carbon::parse($stop['departure_at']);
                                @endphp
                                <article class="day-route-step">
                                    <span class="day-route-step-number">{{ $index + 1 }}</span>
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                        <span class="small text-secondary"><i class="bi bi-clock me-1"></i>{{ $arrival->format('H:i') }}–{{ $departure->format('H:i') }} · переход {{ $stop['travel_minutes'] }} мин.</span>
                                        <span class="badge rounded-pill {{ $scheduleClass }}">{{ $stop['schedule_label'] }}</span>
                                    </div>
                                    <h3 class="h5 mb-2"><a class="text-decoration-none" href="{{ $stop['url'] }}">{{ $stop['name'] }}</a></h3>
                                    <div class="small text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i>{{ $stop['address'] }}</div>
                                    @if($stop['short_description'])<p class="small text-secondary mb-3">{{ \Illuminate\Support\Str::limit($stop['short_description'], 190) }}</p>@endif
                                    @if($stop['sanctities'])<div class="small mb-3"><strong>Святыни:</strong> {{ implode(', ', array_slice($stop['sanctities'], 0, 4)) }}</div>@endif
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge rounded-pill object-type-badge">{{ $stop['type'] ?: 'Паломнический объект' }}</span>
                                        @if($stop['information_current'])<span class="badge rounded-pill text-bg-success">Информация подтверждена</span>@endif
                                        <a class="btn btn-sm btn-light ms-auto" href="{{ $stop['url'] }}">Карточка объекта <i class="bi bi-arrow-right ms-1"></i></a>
                                    </div>
                                    @if($stop['schedule_text'])
                                        <details class="small text-secondary mt-3"><summary>Показать исходное расписание</summary><div class="mt-2">{!! nl2br(e($stop['schedule_text'])) !!}</div></details>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            @auth
                <div class="modal fade" id="saveDayRouteModal" tabindex="-1" aria-labelledby="saveDayRouteLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4 shadow">
                            <form method="POST" action="{{ route('day-route.save') }}">
                                @csrf
                                <div class="modal-header border-0"><h2 class="modal-title h5" id="saveDayRouteLabel">Сохранить маршрут</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body pt-0">
                                    <label class="form-label" for="savedRouteName">Название</label>
                                    <input class="form-control" id="savedRouteName" name="name" value="{{ $result['title'] }}" maxlength="255" required>
                                    <div class="criteria-help mt-2">Маршрут появится в разделе «Мои маршруты» и его можно будет отредактировать.</div>
                                </div>
                                <div class="modal-footer border-0"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Отмена</button><button class="btn btn-pm-gold" type="submit">Сохранить</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @endauth
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/maplibre/maplibre-gl.js') }}"></script>
<script>
(function () {
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const locationLabel = document.getElementById('location_label');
    const locateButton = document.getElementById('locateMeButton');
    const styleUrl = @json(config('palomnik.maps.style_url') ?: route('api.v1.map.style'));
    const initialLat = Number(latitudeInput.value) || 55.751244;
    const initialLng = Number(longitudeInput.value) || 37.618423;

    const selectionMap = new maplibregl.Map({
        container: 'dayRouteMap',
        style: styleUrl,
        center: [initialLng, initialLat],
        zoom: 12,
        attributionControl: false
    });
    selectionMap.addControl(new maplibregl.NavigationControl(), 'bottom-right');
    selectionMap.addControl(new maplibregl.AttributionControl({compact:true}), 'bottom-left');

    const selectionMarker = new maplibregl.Marker({color:'#b08a3e', draggable:true})
        .setLngLat([initialLng, initialLat])
        .addTo(selectionMap);

    function setStartPoint(lng, lat, label) {
        const safeLng = Number(lng);
        const safeLat = Number(lat);
        if (!Number.isFinite(safeLng) || !Number.isFinite(safeLat)) return;
        latitudeInput.value = safeLat.toFixed(7);
        longitudeInput.value = safeLng.toFixed(7);
        selectionMarker.setLngLat([safeLng, safeLat]);
        selectionMap.flyTo({center:[safeLng, safeLat], zoom:14});
        if (label && !locationLabel.value.trim()) locationLabel.value = label;
    }

    selectionMap.on('click', event => setStartPoint(event.lngLat.lng, event.lngLat.lat, 'Точка на карте'));
    selectionMarker.on('dragend', () => {
        const point = selectionMarker.getLngLat();
        setStartPoint(point.lng, point.lat, 'Точка на карте');
    });

    locateButton.addEventListener('click', function () {
        if (!navigator.geolocation) {
            alert('Браузер не поддерживает определение местоположения. Поставьте точку на карте.');
            return;
        }
        locateButton.disabled = true;
        locateButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Определяем...';
        navigator.geolocation.getCurrentPosition(
            position => {
                setStartPoint(position.coords.longitude, position.coords.latitude, 'Моё местоположение');
                locateButton.disabled = false;
                locateButton.innerHTML = '<i class="bi bi-crosshair me-2"></i>Местоположение определено';
            },
            () => {
                locateButton.disabled = false;
                locateButton.innerHTML = '<i class="bi bi-crosshair me-2"></i>Использовать моё местоположение';
                alert('Не удалось получить местоположение. Разрешите доступ браузеру или поставьте точку на карте.');
            },
            {enableHighAccuracy:true, timeout:12000, maximumAge:60000}
        );
    });

    const result = @json($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!result || !document.getElementById('dayRouteResultMap')) return;

    const resultMap = new maplibregl.Map({
        container: 'dayRouteResultMap',
        style: styleUrl,
        center: [Number(result.start.longitude), Number(result.start.latitude)],
        zoom: 12,
        attributionControl: false
    });
    resultMap.addControl(new maplibregl.NavigationControl(), 'bottom-right');
    resultMap.addControl(new maplibregl.AttributionControl({compact:true}), 'bottom-left');

    const bounds = new maplibregl.LngLatBounds();
    const startElement = document.createElement('div');
    startElement.className = 'day-route-start-marker';
    startElement.innerHTML = '<i class="bi bi-flag-fill"></i>';
    new maplibregl.Marker({element:startElement})
        .setLngLat([Number(result.start.longitude), Number(result.start.latitude)])
        .setPopup(new maplibregl.Popup({offset:18}).setText(result.start.label))
        .addTo(resultMap);
    bounds.extend([Number(result.start.longitude), Number(result.start.latitude)]);

    result.stops.forEach(function (stop, index) {
        const element = document.createElement('div');
        element.className = 'day-route-marker';
        element.style.background = stop.marker_color || '#26443b';
        element.textContent = String(index + 1);
        new maplibregl.Marker({element})
            .setLngLat([Number(stop.longitude), Number(stop.latitude)])
            .setPopup(new maplibregl.Popup({offset:18}).setHTML('<strong>'+escapeHtml(stop.name)+'</strong><br><small>'+escapeHtml(stop.address || '')+'</small>'))
            .addTo(resultMap);
        bounds.extend([Number(stop.longitude), Number(stop.latitude)]);
    });

    resultMap.on('load', function () {
        if (result.geometry && result.geometry.type && result.geometry.coordinates) {
            resultMap.addSource('day-route-line', {type:'geojson', data:{type:'Feature', properties:{}, geometry:result.geometry}});
            resultMap.addLayer({id:'day-route-line-outline',type:'line',source:'day-route-line',paint:{'line-color':'#fffdf9','line-width':8,'line-opacity':.9}});
            resultMap.addLayer({id:'day-route-line',type:'line',source:'day-route-line',paint:{'line-color':'#26443b','line-width':4,'line-opacity':.95}});
        }
        if (!bounds.isEmpty()) resultMap.fitBounds(bounds, {padding:55, maxZoom:15});
    });

    document.getElementById('dayRouteResult')?.scrollIntoView({behavior:'smooth', block:'start'});

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value || '';
        return div.innerHTML;
    }
})();
</script>
@endpush
