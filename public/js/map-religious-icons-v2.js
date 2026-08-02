(function () {
    'use strict';

    if (!window.maplibregl || !window.maplibregl.Map) {
        return;
    }

    const LAYER_ID = 'pilgrim-religious-icons';
    const FALLBACK_LAYER_ID = 'pilgrim-religious-fallback';
    const SOURCE_ID = 'pilgrim-objects';
    const IMAGES = {
        temple: 'pm3-temple',
        monastery: 'pm3-monastery',
        chapel: 'pm3-chapel',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function drawPin(ctx, color) {
        ctx.beginPath();
        ctx.moveTo(36, 3);
        ctx.bezierCurveTo(18, 3, 6, 16, 6, 34);
        ctx.bezierCurveTo(6, 54, 27, 72, 36, 81);
        ctx.bezierCurveTo(45, 72, 66, 54, 66, 34);
        ctx.bezierCurveTo(66, 16, 54, 3, 36, 3);
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 5;
        ctx.strokeStyle = '#fffdf9';
        ctx.stroke();
    }

    function drawCross(ctx, x, y, height) {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(x - 1.5, y, 3, height);
        ctx.fillRect(x - 5, y + 4, 10, 3);
    }

    function drawTemple(ctx) {
        ctx.fillStyle = '#ffffff';
        drawCross(ctx, 36, 11, 14);
        ctx.beginPath();
        ctx.moveTo(36, 23);
        ctx.quadraticCurveTo(27, 28, 27, 36);
        ctx.lineTo(45, 36);
        ctx.quadraticCurveTo(45, 28, 36, 23);
        ctx.fill();
        ctx.fillRect(20, 39, 32, 17);
        ctx.fillStyle = '#9b6a19';
        ctx.fillRect(33, 47, 6, 9);
    }

    function drawMonastery(ctx) {
        ctx.fillStyle = '#ffffff';
        drawCross(ctx, 36, 11, 13);
        ctx.fillRect(14, 40, 44, 16);
        ctx.fillRect(17, 31, 12, 25);
        ctx.fillRect(43, 31, 12, 25);
        ctx.beginPath();
        ctx.moveTo(15, 31);
        ctx.lineTo(23, 24);
        ctx.lineTo(31, 31);
        ctx.moveTo(41, 31);
        ctx.lineTo(49, 24);
        ctx.lineTo(57, 31);
        ctx.fill();
        ctx.fillStyle = '#26443b';
        ctx.fillRect(33, 47, 6, 9);
    }

    function drawChapel(ctx) {
        ctx.fillStyle = '#ffffff';
        drawCross(ctx, 36, 12, 14);
        ctx.beginPath();
        ctx.moveTo(19, 39);
        ctx.lineTo(36, 25);
        ctx.lineTo(53, 39);
        ctx.fill();
        ctx.fillRect(24, 38, 24, 18);
        ctx.fillStyle = '#795548';
        ctx.fillRect(33, 47, 6, 9);
    }

    function markerImage(kind, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 72;
        canvas.height = 86;
        const ctx = canvas.getContext('2d');
        drawPin(ctx, color);
        if (kind === 'monastery') drawMonastery(ctx);
        else if (kind === 'chapel') drawChapel(ctx);
        else drawTemple(ctx);
        return ctx.getImageData(0, 0, canvas.width, canvas.height);
    }

    function ensureImages(map) {
        [
            [IMAGES.temple, 'temple', '#9b6a19'],
            [IMAGES.monastery, 'monastery', '#26443b'],
            [IMAGES.chapel, 'chapel', '#795548'],
        ].forEach(function (definition) {
            if (!map.hasImage(definition[0])) {
                map.addImage(definition[0], markerImage(definition[1], definition[2]), {pixelRatio: 2});
            }
        });
    }

    function detailUrl(id) {
        const template = String((window.pilgrimMapConfig || {}).objectDetailUrl || '');
        return template.replace('__ID__', encodeURIComponent(String(id)));
    }

    function popupHtml(item) {
        const sanctities = Array.isArray(item.sanctities) && item.sanctities.length
            ? '<div class="small mb-2" style="color:#8f6a20">' + escapeHtml(item.sanctities.join(', ')) + '</div>'
            : '';
        const description = item.short_description
            ? '<div class="small text-secondary mb-2">' + escapeHtml(item.short_description) + '</div>'
            : '';
        const schedule = item.schedule
            ? '<div class="small text-secondary mb-2"><i class="bi bi-clock me-1"></i>' + escapeHtml(item.schedule) + '</div>'
            : '';

        return '<article class="map-popup">'
            + (item.cover ? '<img src="' + escapeHtml(item.cover) + '" alt="">' : '')
            + '<div class="map-popup-body">'
            + '<div class="small text-secondary mb-1">' + escapeHtml(item.type || 'Паломнический объект') + '</div>'
            + '<div class="fw-bold mb-2">' + escapeHtml(item.name || '') + '</div>'
            + sanctities + description + schedule
            + '<div class="small text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i>' + escapeHtml(item.address || '') + '</div>'
            + '<div class="d-grid gap-2">'
            + '<a class="btn btn-sm btn-pm-green" href="' + escapeHtml(item.url || '#') + '">Открыть карточку</a>'
            + '<button class="btn btn-sm btn-outline-pm" type="button" data-route-object="' + escapeHtml(item.id) + '"><i class="bi bi-signpost-2 me-1"></i>Маршрут отсюда</button>'
            + '</div></div></article>';
    }

    async function showObjectCard(map, id, fallbackLngLat) {
        const url = detailUrl(id);
        if (!url) return;

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const payload = await response.json();
            if (!response.ok || !payload.data) {
                throw new Error(payload.message || 'Не удалось загрузить карточку объекта.');
            }

            const item = payload.data;
            const longitude = Number(item.longitude);
            const latitude = Number(item.latitude);
            const lngLat = Number.isFinite(longitude) && Number.isFinite(latitude)
                ? [longitude, latitude]
                : fallbackLngLat;

            if (!lngLat) return;

            if (map.__pilgrimReligiousPopup) {
                map.__pilgrimReligiousPopup.remove();
            }

            map.__pilgrimReligiousPopup = new window.maplibregl.Popup({offset: 26, maxWidth: '340px'})
                .setLngLat(lngLat)
                .setHTML(popupHtml(item))
                .addTo(map);
        } catch (error) {
            console.error('[map-religious-card]', error);
        }
    }

    function bindInteractions(map) {
        if (map.__pilgrimReligiousInteractionsBound) return;
        map.__pilgrimReligiousInteractionsBound = true;

        const clickHandler = function (event) {
            const feature = event.features && event.features[0];
            const id = feature && feature.properties ? feature.properties.id : null;
            if (!id) return;

            const now = Date.now();
            if (map.__pilgrimLastReligiousClick
                && map.__pilgrimLastReligiousClick.id === String(id)
                && now - map.__pilgrimLastReligiousClick.time < 250) {
                return;
            }
            map.__pilgrimLastReligiousClick = {id: String(id), time: now};
            showObjectCard(map, id, event.lngLat ? [event.lngLat.lng, event.lngLat.lat] : null);
        };

        [LAYER_ID, FALLBACK_LAYER_ID].forEach(function (layerId) {
            map.on('click', layerId, clickHandler);
            map.on('mouseenter', layerId, function () {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', layerId, function () {
                map.getCanvas().style.cursor = '';
            });
        });
    }

    function installLayers(map) {
        if (!map.getSource(SOURCE_ID)) {
            return false;
        }

        if (!map.getLayer(FALLBACK_LAYER_ID)) {
            map.addLayer({
                id: FALLBACK_LAYER_ID,
                type: 'circle',
                source: SOURCE_ID,
                filter: ['!', ['has', 'point_count']],
                paint: {
                    'circle-color': ['coalesce', ['get', 'marker_color'], '#b58a32'],
                    'circle-radius': 10,
                    'circle-stroke-width': 3,
                    'circle-stroke-color': '#fffdf9',
                },
            });
        }

        ensureImages(map);

        if (!map.getLayer(LAYER_ID)) {
            map.addLayer({
                id: LAYER_ID,
                type: 'symbol',
                source: SOURCE_ID,
                filter: ['!', ['has', 'point_count']],
                layout: {
                    'icon-image': [
                        'match', ['get', 'type_slug'],
                        'monastery', IMAGES.monastery,
                        'chapel', IMAGES.chapel,
                        'church', IMAGES.temple,
                        'cathedral', IMAGES.temple,
                        'temple', IMAGES.temple,
                        IMAGES.temple,
                    ],
                    'icon-size': ['interpolate', ['linear'], ['zoom'], 10, 0.86, 14, 1.04, 18, 1.22],
                    'icon-anchor': 'bottom',
                    'icon-allow-overlap': true,
                    'icon-ignore-placement': true,
                    'icon-padding': 0,
                    'visibility': 'visible',
                },
            });
        }

        if (map.getLayer(FALLBACK_LAYER_ID)) map.moveLayer(FALLBACK_LAYER_ID);
        if (map.getLayer(LAYER_ID)) map.moveLayer(LAYER_ID);
        bindInteractions(map);
        return true;
    }

    function installWhenReady(map) {
        let attempts = 0;
        const tryInstall = function () {
            attempts += 1;
            try {
                if (installLayers(map) || attempts >= 50) return;
            } catch (error) {
                console.error('[map-religious-icons-v2]', error);
                if (attempts >= 50) return;
            }
            window.setTimeout(tryInstall, 100);
        };
        tryInstall();
    }

    const mapPrototype = window.maplibregl.Map.prototype;
    const previousAddControl = mapPrototype.addControl;

    mapPrototype.addControl = function () {
        if (!this.__pilgrimReligiousIconsV2Bound) {
            this.__pilgrimReligiousIconsV2Bound = true;
            const map = this;
            map.once('load', function () {
                installWhenReady(map);
            });
        }
        return previousAddControl.apply(this, arguments);
    };
})();
