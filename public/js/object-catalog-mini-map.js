(function () {
    const container = document.getElementById('objectCatalogMiniMap');
    const dataElement = document.getElementById('objectCatalogMiniMapData');

    if (!container || !dataElement) {
        return;
    }

    let objects = [];

    try {
        objects = JSON.parse(dataElement.textContent || '[]');
    } catch (error) {
        objects = [];
    }

    if (!Array.isArray(objects) || objects.length === 0) {
        container.innerHTML = '<div class="object-catalog-map-empty"><div><i class="bi bi-geo-alt fs-2 d-block mb-2"></i>У объектов на этой странице пока нет координат для отображения на карте.</div></div>';
        return;
    }

    if (!window.maplibregl) {
        container.innerHTML = '<div class="object-catalog-map-empty">Не удалось загрузить карту. Откройте большую карту по кнопке выше.</div>';
        return;
    }

    const validObjects = objects.filter((object) => {
        const latitude = Number(object.latitude);
        const longitude = Number(object.longitude);

        return Number.isFinite(latitude)
            && Number.isFinite(longitude)
            && Math.abs(latitude) <= 90
            && Math.abs(longitude) <= 180;
    });

    if (validObjects.length === 0) {
        container.innerHTML = '<div class="object-catalog-map-empty">У объектов на этой странице пока нет корректных координат.</div>';
        return;
    }

    function markerIcon(type) {
        const value = String(type || '').toLocaleLowerCase('ru-RU');

        if (value.includes('монастыр')) return 'bi-buildings-fill';
        if (value.includes('часовн')) return 'bi-house-heart-fill';
        if (value.includes('источник') || value.includes('купель')) return 'bi-droplet-fill';
        if (value.includes('храм') || value.includes('церков') || value.includes('собор')) return 'bi-cross';

        return 'bi-geo-alt-fill';
    }

    function popupContent(object) {
        const wrapper = document.createElement('div');
        wrapper.className = 'object-catalog-map-popup';

        const link = document.createElement('a');
        link.href = object.url || '#';
        link.textContent = object.name || 'Паломнический объект';
        wrapper.appendChild(link);

        if (object.type) {
            const type = document.createElement('div');
            type.className = 'small text-secondary mt-1';
            type.textContent = object.type;
            wrapper.appendChild(type);
        }

        if (object.address) {
            const address = document.createElement('div');
            address.className = 'small mt-2';
            address.textContent = object.address;
            wrapper.appendChild(address);
        }

        return wrapper;
    }

    const first = validObjects[0];
    const map = new maplibregl.Map({
        container,
        style: container.dataset.styleUrl,
        center: [Number(first.longitude), Number(first.latitude)],
        zoom: validObjects.length === 1 ? 14.5 : 10.5,
        attributionControl: false,
        cooperativeGestures: true
    });

    map.addControl(new maplibregl.NavigationControl({showCompass: false}), 'top-right');
    map.addControl(new maplibregl.AttributionControl({compact: true}), 'bottom-left');

    const bounds = new maplibregl.LngLatBounds();

    validObjects.forEach((object) => {
        const longitude = Number(object.longitude);
        const latitude = Number(object.latitude);
        bounds.extend([longitude, latitude]);

        const markerElement = document.createElement('button');
        markerElement.type = 'button';
        markerElement.className = 'object-catalog-map-marker';
        markerElement.setAttribute('aria-label', object.name || 'Паломнический объект');
        markerElement.title = object.name || 'Паломнический объект';

        const icon = document.createElement('i');
        icon.className = `bi ${markerIcon(object.type)}`;
        icon.setAttribute('aria-hidden', 'true');
        markerElement.appendChild(icon);

        const popup = new maplibregl.Popup({offset: 24, closeButton: true})
            .setDOMContent(popupContent(object));

        new maplibregl.Marker({element: markerElement, anchor: 'bottom'})
            .setLngLat([longitude, latitude])
            .setPopup(popup)
            .addTo(map);
    });

    map.on('load', () => {
        if (validObjects.length > 1) {
            map.fitBounds(bounds, {
                padding: {top: 48, right: 48, bottom: 48, left: 48},
                maxZoom: 14,
                duration: 0
            });
        }
    });
})();
