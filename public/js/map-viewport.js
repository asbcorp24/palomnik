(function () {
    'use strict';

    const config = window.pilgrimMapConfig || {};
    const emptyCollection = () => ({type: 'FeatureCollection', features: []});
    const objectCount = document.getElementById('mapObjectCount');
    const objectList = document.getElementById('mapObjectList');
    const poiCount = document.getElementById('mapPoiCount');
    const loadingStatus = document.getElementById('mapLoadingStatus');
    const routeMode = document.getElementById('routeMode');
    const summary = document.getElementById('mapRouteSummary');
    const vicariate = document.getElementById('mapVicariate');
    const deanery = document.getElementById('mapDeanery');

    if (!window.maplibregl || !document.getElementById('pilgrim-map')) {
        return;
    }

    const objectDetails = new Map();
    const pointDetails = new Map();
    let objectRequestController = null;
    let poiRequestController = null;
    let viewportTimer = null;
    let viewportRequestId = 0;
    let routeStopMarkers = [];
    let routeRequestId = 0;
    let activePopup = null;

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

    const initialPoint = config.selectedRoute?.points?.[0] || null;
    const map = new maplibregl.Map({
        container: 'pilgrim-map',
        style: config.styleUrl,
        center: initialPoint
            ? [Number(initialPoint.longitude), Number(initialPoint.latitude)]
            : [37.618423, 55.751244],
        zoom: initialPoint ? 11 : 8.5,
        attributionControl: false,
    });

    map.addControl(new maplibregl.NavigationControl({visualizePitch: true}), 'bottom-right');
    map.addControl(new maplibregl.GeolocateControl({
        positionOptions: {enableHighAccuracy: true},
        trackUserLocation: true,
        showUserHeading: true,
    }), 'bottom-right');
    map.addControl(new maplibregl.FullscreenControl(), 'bottom-right');
    map.addControl(new maplibregl.AttributionControl({
        compact: true,
        customAttribution: config.attribution,
    }), 'bottom-left');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function detailUrl(template, id) {
        return String(template || '').replace('__ID__', encodeURIComponent(String(id)));
    }

    async function fetchJson(url, signal) {
        const response = await fetch(url, {
            headers: {'Accept': 'application/json'},
            signal,
            credentials: 'same-origin',
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok) {
            throw new Error(payload.message || 'Не удалось загрузить данные карты.');
        }

        return payload;
    }

    function viewportParams(includePoiCategories) {
        const bounds = map.getBounds();
        const params = new URLSearchParams({
            min_lat: bounds.getSouth().toFixed(6),
            max_lat: bounds.getNorth().toFixed(6),
            min_lng: bounds.getWest().toFixed(6),
            max_lng: bounds.getEast().toFixed(6),
            zoom: map.getZoom().toFixed(2),
        });

        Object.entries(config.filters || {}).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.set(key, String(value));
            }
        });

        if (includePoiCategories) {
            document.querySelectorAll('[data-poi-category]:checked').forEach(input => {
                params.append('categories[]', input.value);
            });
        }

        return params;
    }

    function scheduleViewportLoad(delay = 180) {
        window.clearTimeout(viewportTimer);
        viewportTimer = window.setTimeout(loadViewport, delay);
    }

    async function loadViewport() {
        if (!map.loaded()) return;

        const requestId = ++viewportRequestId;
        loadingStatus?.classList.remove('d-none');
        objectRequestController?.abort();
        poiRequestController?.abort();
        objectRequestController = new AbortController();
        poiRequestController = new AbortController();

        const objectPromise = fetchJson(
            `${config.objectsUrl}?${viewportParams(false).toString()}`,
            objectRequestController.signal
        );
        const poiPromise = fetchJson(
            `${config.pointsOfInterestUrl}?${viewportParams(true).toString()}`,
            poiRequestController.signal
        );

        try {
            const [objects, points] = await Promise.all([objectPromise, poiPromise]);
            if (requestId !== viewportRequestId) return;

            applyObjects(objects);
            applyPointsOfInterest(points);
            loadingStatus?.classList.add('d-none');
        } catch (error) {
            if (error.name === 'AbortError' || requestId !== viewportRequestId) return;
            loadingStatus?.classList.add('d-none');
            renderLoadError(error.message || 'Ошибка загрузки карты.');
        }
    }

    function applyObjects(collection) {
        const meta = collection.meta || {};
        const source = map.getSource('pilgrim-objects');
        const clusterSource = map.getSource('pilgrim-server-clusters');

        if (meta.mode === 'server_clusters') {
            clusterSource?.setData(collection);
            source?.setData(emptyCollection());
            renderClusterHint(meta);
        } else {
            clusterSource?.setData(emptyCollection());
            source?.setData(collection);
            renderObjectList(collection.features || [], meta);
        }

        const count = meta.visible_objects === null || meta.visible_objects === undefined
            ? `${meta.returned || 0}${meta.truncated ? '+' : ''}`
            : `${meta.visible_objects}${meta.truncated ? '+' : ''}`;
        if (objectCount) objectCount.textContent = count;
    }

    function applyPointsOfInterest(collection) {
        map.getSource('points-of-interest')?.setData(collection);
        const meta = collection.meta || {};
        if (poiCount) {
            poiCount.textContent = meta.mode === 'hidden'
                ? `появятся после масштаба ${meta.min_zoom}`
                : `${meta.returned || 0}${meta.truncated ? '+' : ''}`;
        }
    }

    function renderClusterHint(meta) {
        if (!objectList) return;
        objectList.innerHTML = `
            <div class="map-object-row text-center py-4">
                <i class="bi bi-zoom-in fs-2 text-secondary"></i>
                <p class="small text-secondary mt-3 mb-1">Приблизьте карту, чтобы увидеть отдельные храмы.</p>
                <div class="small text-secondary">Сейчас показано кластеров: ${escapeHtml(meta.returned || 0)}</div>
            </div>`;
    }

    function renderObjectList(features, meta) {
        if (!objectList) return;

        if (!features.length) {
            objectList.innerHTML = `
                <div class="map-object-row text-center py-4">
                    <i class="bi bi-search fs-2 text-secondary"></i>
                    <p class="small text-secondary mt-3 mb-0">В текущей области объекты не найдены.</p>
                </div>`;
            return;
        }

        const rows = features.slice(0, 100).map(feature => {
            const item = feature.properties || {};
            return `
                <button class="map-object-row text-start" type="button" data-map-object="${escapeHtml(item.id)}">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="category-icon flex-shrink-0"><i class="bi bi-buildings"></i></span>
                        <div class="min-w-0">
                            <div class="small text-secondary mb-1">${escapeHtml(item.type || 'Паломнический объект')}</div>
                            <div class="fw-semibold lh-sm mb-2">${escapeHtml(item.name)}</div>
                            <div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.address || '')}</div>
                        </div>
                    </div>
                </button>`;
        }).join('');

        const note = features.length > 100 || meta.truncated
            ? `<div class="small text-secondary py-3 text-center">В списке показаны первые 100 объектов текущей области.</div>`
            : '';
        objectList.innerHTML = rows + note;
    }

    function renderLoadError(message) {
        if (!objectList) return;
        objectList.innerHTML = `
            <div class="alert alert-danger small mb-0">
                <strong>Не удалось обновить карту.</strong><br>${escapeHtml(message)}
                <button class="btn btn-sm btn-outline-danger mt-2" type="button" data-retry-map>Повторить</button>
            </div>`;
    }

    async function loadObjectDetail(id) {
        const key = String(id);
        if (objectDetails.has(key)) return objectDetails.get(key);
        const payload = await fetchJson(detailUrl(config.objectDetailUrl, key));
        objectDetails.set(key, payload.data);
        return payload.data;
    }

    async function loadPointDetail(id) {
        const key = String(id);
        if (pointDetails.has(key)) return pointDetails.get(key);
        const payload = await fetchJson(detailUrl(config.pointOfInterestDetailUrl, key));
        pointDetails.set(key, payload.data);
        return payload.data;
    }

    function objectPopupHtml(item) {
        const sanctities = Array.isArray(item.sanctities) && item.sanctities.length
            ? `<div class="small mb-2" style="color:#8f6a20">${escapeHtml(item.sanctities.join(', '))}</div>`
            : '';
        const description = item.short_description
            ? `<div class="small text-secondary mb-2">${escapeHtml(item.short_description)}</div>`
            : '';
        const schedule = item.schedule
            ? `<div class="small text-secondary mb-2"><i class="bi bi-clock me-1"></i>${escapeHtml(item.schedule)}</div>`
            : '';
        const verified = item.information_verified_at
            ? `<div class="small text-success mb-2"><i class="bi bi-patch-check me-1"></i>Информация подтверждена ${escapeHtml(new Date(item.information_verified_at).toLocaleDateString('ru-RU'))}</div>`
            : '';

        return `<article class="map-popup">
            ${item.cover ? `<img src="${escapeHtml(item.cover)}" alt="">` : ''}
            <div class="map-popup-body">
                <div class="small text-secondary mb-1">${escapeHtml(item.type)}</div>
                <div class="fw-bold mb-2">${escapeHtml(item.name)}</div>
                ${sanctities}${description}${schedule}${verified}
                <div class="small text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.address)}</div>
                <div class="d-grid gap-2">
                    <a class="btn btn-sm btn-pm-green" href="${escapeHtml(item.url)}">Открыть карточку</a>
                    <button class="btn btn-sm btn-outline-pm" type="button" data-route-object="${escapeHtml(item.id)}"><i class="bi bi-signpost-2 me-1"></i>Маршрут отсюда</button>
                </div>
            </div>
        </article>`;
    }

    function pointPopupHtml(item) {
        const description = item.description
            ? `<div class="small text-secondary mb-2">${escapeHtml(item.description)}</div>`
            : '';
        const schedule = item.schedule
            ? `<div class="small text-secondary mb-2"><i class="bi bi-clock me-1"></i>${escapeHtml(item.schedule)}</div>`
            : '';
        const website = item.website
            ? `<a class="btn btn-sm btn-light" href="${escapeHtml(item.website)}" target="_blank" rel="noopener">Сайт</a>`
            : '';
        const phone = item.phone
            ? `<a class="btn btn-sm btn-light" href="tel:${escapeHtml(String(item.phone).replace(/[^+0-9]/g, ''))}">Позвонить</a>`
            : '';

        return `<article class="map-popup"><div class="map-popup-body">
            <div class="small fw-semibold mb-1" style="color:${escapeHtml(item.marker_color)}">${escapeHtml(item.category_label)}</div>
            <div class="fw-bold mb-2">${escapeHtml(item.name)}</div>
            ${description}
            <div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.address)}</div>
            ${schedule}
            <div class="small mb-3">Рядом с: <strong>${escapeHtml(item.base_object_name || '')}</strong></div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-pm-green" href="${escapeHtml(item.base_object_url || '#')}">Открыть объект</a>${website}${phone}
            </div>
        </div></article>`;
    }

    async function showObject(id, fly = true) {
        try {
            const item = await loadObjectDetail(id);
            if (fly) map.easeTo({center: [Number(item.longitude), Number(item.latitude)], zoom: Math.max(map.getZoom(), 14)});
            activePopup?.remove();
            activePopup = new maplibregl.Popup({offset: 22, maxWidth: '340px'})
                .setLngLat([Number(item.longitude), Number(item.latitude)])
                .setHTML(objectPopupHtml(item))
                .addTo(map);
        } catch (error) {
            renderLoadError(error.message || 'Не удалось открыть карточку объекта.');
        }
    }

    async function showPointOfInterest(id, fly = true) {
        try {
            const item = await loadPointDetail(id);
            if (fly) map.easeTo({center: [Number(item.longitude), Number(item.latitude)], zoom: Math.max(map.getZoom(), 16)});
            activePopup?.remove();
            activePopup = new maplibregl.Popup({offset: 18, maxWidth: '340px'})
                .setLngLat([Number(item.longitude), Number(item.latitude)])
                .setHTML(pointPopupHtml(item))
                .addTo(map);
        } catch (error) {
            renderLoadError(error.message || 'Не удалось открыть точку интереса.');
        }
    }

    function addRasterLayers() {
        if (config.satelliteUrl) {
            map.addSource('satellite', {type: 'raster', tiles: [config.satelliteUrl], tileSize: 256, attribution: config.attribution});
            map.addLayer({id: 'satellite', type: 'raster', source: 'satellite', layout: {visibility: 'none'}});
        }
        if (config.historicUrl) {
            map.addSource('historic', {type: 'raster', tiles: [config.historicUrl], tileSize: 256, attribution: config.attribution});
            map.addLayer({id: 'historic', type: 'raster', source: 'historic', layout: {visibility: 'none'}, paint: {'raster-opacity': 0.88}});
        }
    }

    function addObjectLayers() {
        map.addSource('pilgrim-server-clusters', {type: 'geojson', data: emptyCollection()});
        map.addLayer({
            id: 'pilgrim-server-clusters',
            type: 'circle',
            source: 'pilgrim-server-clusters',
            paint: {
                'circle-color': '#26443b',
                'circle-radius': ['step', ['get', 'point_count'], 18, 10, 23, 40, 29, 150, 35],
                'circle-stroke-width': 3,
                'circle-stroke-color': '#fffdf9',
            },
        });
        map.addLayer({
            id: 'pilgrim-server-cluster-count',
            type: 'symbol',
            source: 'pilgrim-server-clusters',
            layout: {'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12},
            paint: {'text-color': '#ffffff'},
        });

        map.addSource('pilgrim-objects', {
            type: 'geojson',
            data: emptyCollection(),
            cluster: true,
            clusterMaxZoom: 14,
            clusterRadius: 46,
        });
        map.addLayer({
            id: 'pilgrim-clusters',
            type: 'circle',
            source: 'pilgrim-objects',
            filter: ['has', 'point_count'],
            paint: {
                'circle-color': '#26443b',
                'circle-radius': ['step', ['get', 'point_count'], 18, 10, 23, 40, 29],
                'circle-stroke-width': 3,
                'circle-stroke-color': '#fffdf9',
            },
        });
        map.addLayer({
            id: 'pilgrim-cluster-count',
            type: 'symbol',
            source: 'pilgrim-objects',
            filter: ['has', 'point_count'],
            layout: {'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12},
            paint: {'text-color': '#ffffff'},
        });
        map.addLayer({
            id: 'pilgrim-points',
            type: 'circle',
            source: 'pilgrim-objects',
            filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': ['coalesce', ['get', 'marker_color'], '#b58a32'],
                'circle-radius': 9,
                'circle-stroke-width': 3,
                'circle-stroke-color': '#fffdf9',
            },
        });
    }

    function addPoiLayers() {
        map.addSource('points-of-interest', {type: 'geojson', data: emptyCollection()});
        map.addLayer({
            id: 'points-of-interest',
            type: 'circle',
            source: 'points-of-interest',
            paint: {
                'circle-color': ['coalesce', ['get', 'marker_color'], '#7c3aed'],
                'circle-radius': 7,
                'circle-stroke-width': 2.5,
                'circle-stroke-color': '#fffdf9',
            },
        });
    }

    map.on('load', () => {
        addRasterLayers();
        addObjectLayers();
        addPoiLayers();

        if (config.selectedRoute?.points?.length >= 2) {
            buildPublishedRoute(config.selectedRoute);
        } else if (config.focusedPointOfInterestId) {
            showPointOfInterest(config.focusedPointOfInterestId, true);
        }

        scheduleViewportLoad(0);
    });

    map.on('moveend', () => scheduleViewportLoad());

    map.on('click', 'pilgrim-server-clusters', event => {
        const feature = event.features?.[0];
        if (!feature) return;
        map.easeTo({
            center: feature.geometry.coordinates,
            zoom: Number(feature.properties.target_zoom || Math.floor(map.getZoom()) + 2),
        });
    });

    map.on('click', 'pilgrim-clusters', async event => {
        const feature = event.features?.[0];
        if (!feature) return;
        const zoom = await map.getSource('pilgrim-objects').getClusterExpansionZoom(feature.properties.cluster_id);
        map.easeTo({center: feature.geometry.coordinates, zoom});
    });

    map.on('click', 'pilgrim-points', event => {
        const feature = event.features?.[0];
        if (feature?.properties?.id) showObject(feature.properties.id, false);
    });

    map.on('click', 'points-of-interest', event => {
        const feature = event.features?.[0];
        if (feature?.properties?.id) showPointOfInterest(feature.properties.id, false);
    });

    [
        'pilgrim-server-clusters',
        'pilgrim-clusters',
        'pilgrim-points',
        'points-of-interest',
    ].forEach(layer => {
        map.on('mouseenter', layer, () => map.getCanvas().style.cursor = 'pointer');
        map.on('mouseleave', layer, () => map.getCanvas().style.cursor = '');
    });

    document.addEventListener('click', event => {
        const objectButton = event.target.closest('[data-map-object]');
        if (objectButton) {
            showObject(objectButton.dataset.mapObject, true);
            return;
        }

        const routeButton = event.target.closest('[data-route-object]');
        if (routeButton) {
            loadObjectDetail(routeButton.dataset.routeObject).then(buildRoute).catch(error => {
                renderLoadError(error.message || 'Не удалось построить маршрут.');
            });
            return;
        }

        if (event.target.closest('[data-retry-map]')) {
            scheduleViewportLoad(0);
        }
    });

    document.querySelectorAll('[data-poi-category]').forEach(input => {
        input.addEventListener('change', () => scheduleViewportLoad(0));
    });

    document.querySelectorAll('[data-layer-mode]').forEach(button => button.addEventListener('click', () => {
        const mode = button.dataset.layerMode;
        ['satellite', 'historic'].forEach(id => {
            if (map.getLayer(id)) {
                map.setLayoutProperty(id, 'visibility', mode === id ? 'visible' : 'none');
            }
        });
        document.querySelectorAll('[data-layer-mode]').forEach(item => item.classList.toggle('active', item === button));
    }));

    routeMode?.addEventListener('change', () => {
        if (config.selectedRoute?.points?.length >= 2 && map.loaded()) {
            buildPublishedRoute(config.selectedRoute);
        }
    });

    function setRouteGeometry(routeGeometry) {
        const feature = {type: 'Feature', properties: {}, geometry: routeGeometry};
        if (map.getSource('active-route')) {
            map.getSource('active-route').setData(feature);
            return;
        }

        map.addSource('active-route', {type: 'geojson', data: feature});
        const beforeLayer = map.getLayer('pilgrim-points') ? 'pilgrim-points' : undefined;
        map.addLayer({
            id: 'active-route-outline',
            type: 'line',
            source: 'active-route',
            layout: {'line-join': 'round', 'line-cap': 'round'},
            paint: {'line-color': '#fffdf9', 'line-width': 9, 'line-opacity': 0.94},
        }, beforeLayer);
        map.addLayer({
            id: 'active-route',
            type: 'line',
            source: 'active-route',
            layout: {'line-join': 'round', 'line-cap': 'round'},
            paint: {'line-color': '#b58a32', 'line-width': 5.5},
        }, beforeLayer);
    }

    function fitRoute(routeGeometry) {
        const coordinates = routeGeometry.coordinates || [];
        if (!coordinates.length) return;
        const bounds = coordinates.reduce(
            (box, coordinate) => box.extend(coordinate),
            new maplibregl.LngLatBounds(coordinates[0], coordinates[0])
        );
        map.fitBounds(bounds, {padding: {top: 70, bottom: 120, left: 70, right: 70}, maxZoom: 16});
    }

    function removeRouteStopMarkers() {
        routeStopMarkers.forEach(marker => marker.remove());
        routeStopMarkers = [];
    }

    function showRouteStops(route) {
        removeRouteStopMarkers();
        routeStopMarkers = route.points.map(point => {
            const element = document.createElement('button');
            element.type = 'button';
            element.className = 'route-stop-marker';
            element.title = `${point.number}. ${point.name}`;
            element.innerHTML = `<span>${escapeHtml(point.number)}</span>`;
            const popup = new maplibregl.Popup({offset: 24, maxWidth: '300px'}).setHTML(
                `<div class="map-popup-body"><div class="small text-secondary mb-1">Точка ${escapeHtml(point.number)}</div><div class="fw-bold mb-2">${escapeHtml(point.name)}</div><div class="small text-secondary mb-3">${escapeHtml(point.address || '')}</div><a class="btn btn-sm btn-pm-green w-100" href="${escapeHtml(point.url)}">Открыть объект</a></div>`
            );
            return new maplibregl.Marker({element, anchor: 'bottom'})
                .setLngLat([Number(point.longitude), Number(point.latitude)])
                .setPopup(popup)
                .addTo(map);
        });
    }

    async function requestRoute(locations) {
        const response = await fetch(config.routeUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({mode: routeMode.value, locations}),
        });
        let payload = {};
        try { payload = await response.json(); } catch (error) { payload = {}; }
        if (!response.ok) throw new Error(payload.message || 'Маршрут не найден.');
        return payload.data;
    }

    async function buildPublishedRoute(route) {
        clearRoute(false);
        const requestId = ++routeRequestId;
        showRouteStops(route);
        summary.className = 'map-route-summary alert alert-light border shadow-sm mb-0';
        summary.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>Строим путь между точками маршрута…</div>';
        const locations = route.points.map(point => ({latitude: Number(point.latitude), longitude: Number(point.longitude)}));

        try {
            const routeData = await requestRoute(locations);
            if (requestId !== routeRequestId) return;
            setRouteGeometry(routeData.geometry);
            fitRoute(routeData.geometry);
            const km = (routeData.distance_meters / 1000).toFixed(1);
            const minutes = Math.max(1, Math.round(routeData.duration_seconds / 60));
            summary.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-3"><div><strong>${escapeHtml(route.title)}</strong><div class="small text-secondary mt-1">${route.points.length} точек · ${km} км · примерно ${minutes} мин.</div></div><div class="d-flex align-items-center gap-2"><a class="btn btn-sm btn-outline-pm" href="${escapeHtml(route.url)}">Описание</a><button class="btn-close" type="button" aria-label="Закрыть"></button></div></div>`;
            summary.querySelector('.btn-close').addEventListener('click', () => clearRoute());
        } catch (error) {
            if (requestId !== routeRequestId) return;
            const fallbackGeometry = {type: 'LineString', coordinates: route.points.map(point => [Number(point.longitude), Number(point.latitude)])};
            setRouteGeometry(fallbackGeometry);
            fitRoute(fallbackGeometry);
            summary.className = 'map-route-summary alert alert-warning shadow-sm mb-0';
            summary.innerHTML = `<div class="d-flex justify-content-between align-items-start gap-3"><div><strong>${escapeHtml(route.title)}</strong><div class="small mt-1">Сервис маршрутизации недоступен. Показана прямая линия между точками.</div><div class="small text-secondary mt-1">${escapeHtml(error.message || '')}</div></div><button class="btn-close" type="button" aria-label="Закрыть"></button></div>`;
            summary.querySelector('.btn-close').addEventListener('click', () => clearRoute());
        }
    }

    async function buildRoute(item) {
        clearRoute(false);
        const requestId = ++routeRequestId;
        summary.className = 'map-route-summary alert alert-light border shadow-sm mb-0';
        summary.innerHTML = '<div class="d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>Определяем местоположение и строим маршрут…</div>';

        try {
            const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {enableHighAccuracy: true, timeout: 12000}));
            const routeData = await requestRoute([
                {latitude: position.coords.latitude, longitude: position.coords.longitude},
                {latitude: Number(item.latitude), longitude: Number(item.longitude)},
            ]);
            if (requestId !== routeRequestId) return;
            setRouteGeometry(routeData.geometry);
            fitRoute(routeData.geometry);
            const km = (routeData.distance_meters / 1000).toFixed(1);
            const minutes = Math.max(1, Math.round(routeData.duration_seconds / 60));
            summary.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-3"><div><strong>${escapeHtml(item.name)}</strong><div class="small text-secondary mt-1">${km} км · примерно ${minutes} мин.</div></div><button class="btn-close" type="button" aria-label="Закрыть"></button></div>`;
            summary.querySelector('.btn-close').addEventListener('click', () => clearRoute());
        } catch (error) {
            if (requestId !== routeRequestId) return;
            summary.className = 'map-route-summary alert alert-danger shadow-sm mb-0';
            summary.textContent = error.message || 'Не удалось построить маршрут.';
        }
    }

    function clearRoute(hideSummary = true) {
        routeRequestId++;
        if (map.getLayer('active-route')) map.removeLayer('active-route');
        if (map.getLayer('active-route-outline')) map.removeLayer('active-route-outline');
        if (map.getSource('active-route')) map.removeSource('active-route');
        removeRouteStopMarkers();
        if (hideSummary) summary.classList.add('d-none');
    }
})();
