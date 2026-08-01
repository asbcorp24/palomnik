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

    async function loadFocusedEnvironment(map, directFlyTo) {
        try {
            const response = await fetch(focusedObjectUrl(focusSlug), {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
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
