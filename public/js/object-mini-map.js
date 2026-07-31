(function () {
    const pageTitle = document.querySelector('.page-hero .section-title');
    const sidebar = document.querySelector('section.section-space aside.col-lg-4 > .d-grid.position-sticky');

    if (!pageTitle || !sidebar || document.getElementById('objectMiniMap')) {
        return;
    }

    const routeCard = Array.from(sidebar.children).find((element) => {
        return element.classList.contains('info-card')
            && element.querySelector('h2')?.textContent.trim() === 'Построить маршрут';
    });
    const routeLink = routeCard?.querySelector('a[href*="yandex.ru/maps"][href*="rtext="]');

    if (!routeCard || !routeLink) {
        return;
    }

    let latitude;
    let longitude;

    try {
        const routeUrl = new URL(routeLink.href, window.location.origin);
        const routeText = routeUrl.searchParams.get('rtext') || '';
        const destination = routeText.split('~').pop()?.replace(/^~/, '') || '';
        const coordinates = destination.split(',').map(Number);
        latitude = coordinates[0];
        longitude = coordinates[1];
    } catch (error) {
        return;
    }

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const currentScriptUrl = new URL(document.currentScript?.src || window.location.href, window.location.origin);
    const applicationPath = currentScriptUrl.pathname.replace(/\/js\/object-mini-map\.js$/, '');
    const localUrl = (path) => `${currentScriptUrl.origin}${applicationPath}${path}`;
    const objectName = pageTitle.textContent.trim();
    const address = document.querySelector('.page-hero .section-lead')?.textContent.replace(/\s+/g, ' ').trim() || '';
    const objectType = document.querySelector('.object-type-badge')?.textContent.trim().toLocaleLowerCase('ru-RU') || '';
    const existingMapLink = Array.from(sidebar.querySelectorAll('a')).find((link) => link.textContent.includes('Открыть на общей карте'));
    const largeMapUrl = new URL(existingMapLink?.href || localUrl('/map'), window.location.origin);
    largeMapUrl.searchParams.set('q', objectName);

    if (existingMapLink) {
        existingMapLink.href = largeMapUrl.toString();
    }

    const markerIcon = objectType.includes('монастыр')
        ? 'bi-buildings-fill'
        : objectType.includes('часовн')
            ? 'bi-house-heart-fill'
            : (objectType.includes('источник') || objectType.includes('купель'))
                ? 'bi-droplet-fill'
                : (objectType.includes('храм') || objectType.includes('церков') || objectType.includes('собор'))
                    ? 'bi-cross'
                    : 'bi-geo-alt-fill';

    const style = document.createElement('style');
    style.textContent = `
        .object-mini-map-card{overflow:hidden;padding:0}
        .object-mini-map{position:relative;width:100%;height:270px;background:#ebe7df}
        .object-mini-map .maplibregl-canvas{outline:none}
        .object-mini-map-marker{width:42px;height:42px;display:flex;align-items:center;justify-content:center;border:3px solid #fffdf9;border-radius:50% 50% 50% 12%;color:#fff;font-size:19px;box-shadow:0 8px 22px rgba(24,35,31,.32);transform:rotate(-45deg);background:#b08a3e}
        .object-mini-map-marker i{transform:rotate(45deg)}
        .object-mini-map-caption{padding:15px 17px 17px}
        .object-mini-map-error{height:100%;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;color:#746c64;font-size:.875rem}
        @media(max-width:991.98px){.object-mini-map{height:320px}}
    `;
    document.head.appendChild(style);

    const mapLibreCss = document.createElement('link');
    mapLibreCss.rel = 'stylesheet';
    mapLibreCss.href = localUrl('/assets/vendor/maplibre/maplibre-gl.css');
    document.head.appendChild(mapLibreCss);

    const card = document.createElement('div');
    card.className = 'info-card object-mini-map-card';
    card.innerHTML = `
        <div id="objectMiniMap" class="object-mini-map" role="img"></div>
        <div class="object-mini-map-caption">
            <div class="d-flex align-items-start gap-2 mb-3">
                <i class="bi bi-geo-alt-fill mt-1" style="color:#b08a3e"></i>
                <div>
                    <div class="fw-semibold">Расположение</div>
                    <div class="small text-secondary mt-1" data-object-mini-map-address></div>
                </div>
            </div>
            <a class="btn btn-outline-pm w-100" data-object-mini-map-link>
                <i class="bi bi-arrows-fullscreen me-2"></i>Открыть на большой карте
            </a>
        </div>
    `;

    card.querySelector('[data-object-mini-map-address]').textContent = address;
    card.querySelector('[data-object-mini-map-link]').href = largeMapUrl.toString();
    const mapContainer = card.querySelector('#objectMiniMap');
    mapContainer.setAttribute('aria-label', `Карта расположения объекта ${objectName}`);
    sidebar.insertBefore(card, routeCard);

    const initializeMap = () => {
        if (!window.maplibregl) {
            mapContainer.innerHTML = '<div class="object-mini-map-error">Не удалось загрузить мини-карту.</div>';
            return;
        }

        const map = new maplibregl.Map({
            container: mapContainer,
            style: localUrl('/api/v1/map/style.json'),
            center: [longitude, latitude],
            zoom: 14.7,
            attributionControl: false,
            cooperativeGestures: true
        });

        map.addControl(new maplibregl.NavigationControl({showCompass: false}), 'top-right');
        map.addControl(new maplibregl.AttributionControl({compact: true}), 'bottom-left');

        const markerElement = document.createElement('div');
        markerElement.className = 'object-mini-map-marker';
        markerElement.innerHTML = `<i class="bi ${markerIcon}" aria-hidden="true"></i>`;
        markerElement.title = objectName;

        new maplibregl.Marker({element: markerElement, anchor: 'bottom'})
            .setLngLat([longitude, latitude])
            .addTo(map);
    };

    if (window.maplibregl) {
        initializeMap();
        return;
    }

    const existingScript = document.querySelector('script[data-object-maplibre]');
    if (existingScript) {
        existingScript.addEventListener('load', initializeMap, {once: true});
        existingScript.addEventListener('error', initializeMap, {once: true});
        return;
    }

    const mapLibreScript = document.createElement('script');
    mapLibreScript.src = localUrl('/assets/vendor/maplibre/maplibre-gl.js');
    mapLibreScript.dataset.objectMaplibre = '1';
    mapLibreScript.addEventListener('load', initializeMap, {once: true});
    mapLibreScript.addEventListener('error', initializeMap, {once: true});
    document.head.appendChild(mapLibreScript);
})();
