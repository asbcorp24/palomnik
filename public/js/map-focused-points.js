(function () {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const focusSlug = String(params.get('focus_slug') || '').trim();

    if (!focusSlug || !window.maplibregl || !window.maplibregl.Map) {
        return;
    }

    const mapPrototype = window.maplibregl.Map.prototype;
    const previousAddControl = mapPrototype.addControl;

    function focusedObjectUrl(slug) {
        return '/api/map/object-by-slug/' + encodeURIComponent(slug);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function pointIconClass(category) {
        if (category === 'parking') return 'bi-p-circle-fill';
        if (category === 'hotel') return 'bi-building-fill';
        if (category === 'cafe') return 'bi-cup-hot-fill';
        return 'bi-star-fill';
    }

    function pointPopupHtml(point) {
        const description = point.description
            ? '<div class="small text-secondary mb-2">' + escapeHtml(point.description) + '</div>'
            : '';
        const schedule = point.schedule
            ? '<div class="small text-secondary mb-2"><i class="bi bi-clock me-1"></i>' + escapeHtml(point.schedule) + '</div>'
            : '';
        const phone = point.phone
            ? '<a class="btn btn-sm btn-light" href="tel:'
                + escapeHtml(String(point.phone).replace(/[^+0-9]/g, ''))
                + '">Позвонить</a>'
            : '';
        const website = point.website
            ? '<a class="btn btn-sm btn-light" href="'
                + escapeHtml(point.website)
                + '" target="_blank" rel="noopener">Сайт</a>'
            : '';

        return '<article class="map-popup"><div class="map-popup-body">'
            + '<div class="small fw-semibold mb-1">' + escapeHtml(point.category_label || 'Точка интереса') + '</div>'
            + '<div class="fw-bold mb-2">' + escapeHtml(point.name || point.category_label || 'Точка интереса') + '</div>'
            + description
            + '<div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>' + escapeHtml(point.address || '') + '</div>'
            + schedule
            + ((phone || website) ? '<div class="d-flex gap-2 flex-wrap">' + website + phone + '</div>' : '')
            + '</div></article>';
    }

    function clearFocusedPointMarkers(map) {
        const markers = Array.isArray(map.__pilgrimFocusedPointMarkers)
            ? map.__pilgrimFocusedPointMarkers
            : [];
        markers.forEach(marker => marker.remove());
        map.__pilgrimFocusedPointMarkers = [];
    }

    function addFocusedPointMarkers(map, points) {
        clearFocusedPointMarkers(map);

        map.__pilgrimFocusedPointMarkers = points.map(point => {
            const category = String(point.category || 'attraction');
            const element = document.createElement('button');
            element.type = 'button';
            element.className = 'focused-poi-marker focused-poi-marker--' + category;
            element.title = point.name || point.category_label || 'Точка интереса';
            element.setAttribute('aria-label', element.title);
            element.innerHTML = '<i class="bi ' + pointIconClass(category) + '"></i>';

            const popup = new window.maplibregl.Popup({offset: 24, maxWidth: '330px'})
                .setHTML(pointPopupHtml(point));

            return new window.maplibregl.Marker({
                element,
                anchor: 'bottom',
            })
                .setLngLat([Number(point.longitude), Number(point.latitude)])
                .setPopup(popup)
                .addTo(map);
        });
    }

    async function loadFocusedEnvironment(map, directFlyTo) {
        try {
            const response = await fetch(focusedObjectUrl(focusSlug), {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const payload = await response.json();

            if (!response.ok || !payload.data) {
                throw new Error(payload.message || 'Не удалось получить выбранный объект.');
            }

            const item = payload.data;
            const objectLongitude = Number(item.longitude);
            const objectLatitude = Number(item.latitude);
            const points = Array.isArray(item.nearby_points)
                ? item.nearby_points.filter(point => {
                    return Number.isFinite(Number(point.longitude))
                        && Number.isFinite(Number(point.latitude));
                })
                : [];

            if (!Number.isFinite(objectLongitude) || !Number.isFinite(objectLatitude)) {
                return;
            }

            map.__pilgrimFocusedPointsResolved = true;
            addFocusedPointMarkers(map, points);

            const poiCount = document.getElementById('mapPoiCount');
            if (poiCount && points.length) {
                poiCount.textContent = points.length + ' рядом с выбранным объектом';
            }

            if (!points.length) {
                const pending = map.__pilgrimPendingFocusFly;
                if (pending) {
                    directFlyTo(pending.options, pending.eventData);
                } else {
                    directFlyTo({
                        center: [objectLongitude, objectLatitude],
                        zoom: Math.min(18.5, map.getMaxZoom()),
                        speed: 1.35,
                        curve: 1.25,
                        essential: true,
                    });
                }
                return;
            }

            const bounds = new window.maplibregl.LngLatBounds(
                [objectLongitude, objectLatitude],
                [objectLongitude, objectLatitude]
            );

            points.forEach(point => {
                bounds.extend([Number(point.longitude), Number(point.latitude)]);
            });

            window.setTimeout(() => {
                map.fitBounds(bounds, {
                    padding: {
                        top: 90,
                        right: window.innerWidth < 768 ? 45 : 110,
                        bottom: 130,
                        left: window.innerWidth < 768 ? 45 : 110,
                    },
                    maxZoom: Math.min(16.5, map.getMaxZoom()),
                    duration: 1300,
                    essential: true,
                });
            }, 120);
        } catch (error) {
            console.error('[map-focused-points]', error);
            map.__pilgrimFocusedPointsResolved = true;

            const pending = map.__pilgrimPendingFocusFly;
            if (pending) {
                directFlyTo(pending.options, pending.eventData);
            }
        }
    }

    mapPrototype.addControl = function () {
        if (!this.__pilgrimFocusedPointsBound) {
            this.__pilgrimFocusedPointsBound = true;

            const map = this;
            const directFlyTo = map.flyTo.bind(map);

            map.flyTo = function (options, eventData) {
                const requestedZoom = Number(options && options.zoom);
                const isInitialFocusedZoom = !map.__pilgrimFocusedPointsResolved
                    && Number.isFinite(requestedZoom)
                    && requestedZoom >= 18;

                if (isInitialFocusedZoom) {
                    map.__pilgrimPendingFocusFly = {options, eventData};
                    return map;
                }

                return directFlyTo(options, eventData);
            };

            map.once('load', function () {
                loadFocusedEnvironment(map, directFlyTo);
            });
        }

        return previousAddControl.apply(this, arguments);
    };
})();
