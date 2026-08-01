(function () {
    'use strict';

    const SCRIPT_FLAG = '__pilgrimMapEnhancementsLoaded';

    function currentObjectSlug() {
        const match = window.location.pathname.match(/^\/objects\/([^/]+)\/?$/i);
        return match ? decodeURIComponent(match[1]) : null;
    }

    function enhanceObjectPageMapLinks() {
        const slug = currentObjectSlug();
        if (!slug) return;

        document.querySelectorAll('a[href]').forEach(anchor => {
            let url;

            try {
                url = new URL(anchor.getAttribute('href'), window.location.origin);
            } catch (error) {
                return;
            }

            if (url.origin !== window.location.origin || url.pathname.replace(/\/+$/, '') !== '/map') {
                return;
            }

            url.searchParams.set('focus_slug', slug);
            anchor.href = url.pathname + url.search + url.hash;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceObjectPageMapLinks, {once: true});
    } else {
        enhanceObjectPageMapLinks();
    }

    if (window[SCRIPT_FLAG]) return;
    window[SCRIPT_FLAG] = true;

    if (!window.maplibregl || !window.maplibregl.Map) return;

    const markerImages = {
        'pm-temple': ['temple', '#9b6a19'],
        'pm-monastery': ['monastery', '#26443b'],
        'pm-chapel': ['chapel', '#795548'],
        'pm-parking': ['parking', '#2563eb'],
        'pm-hotel': ['hotel', '#0f766e'],
        'pm-cafe': ['cafe', '#b45309'],
        'pm-attraction': ['attraction', '#7c3aed'],
    };

    function drawPin(ctx, color) {
        ctx.save();
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
        ctx.restore();
    }

    function setupWhiteStroke(ctx, width) {
        ctx.strokeStyle = '#ffffff';
        ctx.fillStyle = '#ffffff';
        ctx.lineWidth = width;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    function drawTemple(ctx) {
        ctx.save();
        setupWhiteStroke(ctx, 4);
        ctx.beginPath();
        ctx.moveTo(36, 15);
        ctx.lineTo(36, 51);
        ctx.moveTo(31, 21);
        ctx.lineTo(41, 21);
        ctx.moveTo(26, 31);
        ctx.lineTo(46, 31);
        ctx.moveTo(28, 43);
        ctx.lineTo(44, 38);
        ctx.stroke();
        ctx.restore();
    }

    function drawMonastery(ctx) {
        ctx.save();
        setupWhiteStroke(ctx, 3);
        ctx.strokeRect(21, 36, 30, 17);
        ctx.beginPath();
        ctx.moveTo(26, 36);
        ctx.quadraticCurveTo(26, 27, 31, 27);
        ctx.quadraticCurveTo(36, 27, 36, 36);
        ctx.moveTo(36, 36);
        ctx.quadraticCurveTo(36, 23, 42, 23);
        ctx.quadraticCurveTo(48, 23, 48, 36);
        ctx.moveTo(31, 20);
        ctx.lineTo(31, 28);
        ctx.moveTo(28, 23);
        ctx.lineTo(34, 23);
        ctx.moveTo(42, 15);
        ctx.lineTo(42, 24);
        ctx.moveTo(38, 19);
        ctx.lineTo(46, 19);
        ctx.stroke();
        ctx.fillRect(27, 43, 4, 10);
        ctx.fillRect(40, 43, 5, 10);
        ctx.restore();
    }

    function drawChapel(ctx) {
        ctx.save();
        setupWhiteStroke(ctx, 3.5);
        ctx.beginPath();
        ctx.moveTo(22, 36);
        ctx.lineTo(36, 24);
        ctx.lineTo(50, 36);
        ctx.stroke();
        ctx.strokeRect(25, 36, 22, 17);
        ctx.fillRect(33, 43, 6, 10);
        ctx.beginPath();
        ctx.moveTo(36, 15);
        ctx.lineTo(36, 27);
        ctx.moveTo(31, 20);
        ctx.lineTo(41, 20);
        ctx.stroke();
        ctx.restore();
    }

    function drawParking(ctx) {
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.font = '700 34px Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('P', 36, 35);
        ctx.restore();
    }

    function drawHotel(ctx) {
        ctx.save();
        setupWhiteStroke(ctx, 4);
        ctx.beginPath();
        ctx.moveTo(20, 22);
        ctx.lineTo(20, 50);
        ctx.moveTo(20, 39);
        ctx.lineTo(52, 39);
        ctx.lineTo(52, 50);
        ctx.moveTo(20, 49);
        ctx.lineTo(52, 49);
        ctx.stroke();
        ctx.fillRect(25, 30, 10, 7);
        ctx.fillRect(37, 30, 13, 7);
        ctx.restore();
    }

    function drawCafe(ctx) {
        ctx.save();
        setupWhiteStroke(ctx, 4);
        ctx.beginPath();
        ctx.moveTo(23, 28);
        ctx.lineTo(23, 42);
        ctx.quadraticCurveTo(23, 50, 34, 50);
        ctx.quadraticCurveTo(45, 50, 45, 42);
        ctx.lineTo(45, 28);
        ctx.closePath();
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(46, 32);
        ctx.quadraticCurveTo(57, 32, 54, 41);
        ctx.quadraticCurveTo(52, 45, 46, 43);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(28, 21);
        ctx.quadraticCurveTo(25, 16, 30, 13);
        ctx.moveTo(39, 21);
        ctx.quadraticCurveTo(36, 16, 41, 13);
        ctx.stroke();
        ctx.restore();
    }

    function drawAttraction(ctx) {
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.translate(36, 35);
        ctx.beginPath();

        for (let index = 0; index < 10; index++) {
            const angle = -Math.PI / 2 + index * Math.PI / 5;
            const radius = index % 2 === 0 ? 17 : 7;
            const x = Math.cos(angle) * radius;
            const y = Math.sin(angle) * radius;
            if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        }

        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function createMarkerImage(kind, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 72;
        canvas.height = 86;
        const ctx = canvas.getContext('2d');

        drawPin(ctx, color);

        if (kind === 'monastery') drawMonastery(ctx);
        else if (kind === 'chapel') drawChapel(ctx);
        else if (kind === 'parking') drawParking(ctx);
        else if (kind === 'hotel') drawHotel(ctx);
        else if (kind === 'cafe') drawCafe(ctx);
        else if (kind === 'attraction') drawAttraction(ctx);
        else drawTemple(ctx);

        return ctx.getImageData(0, 0, canvas.width, canvas.height);
    }

    function ensureMarkerImages(map) {
        Object.entries(markerImages).forEach(([name, definition]) => {
            if (!map.hasImage(name)) {
                map.addImage(name, createMarkerImage(definition[0], definition[1]), {pixelRatio: 2});
            }
        });
    }

    function replaceLayerWithSymbol(map, layerId, definition) {
        if (!map.getLayer(layerId) || !map.getSource(definition.source)) return;

        map.removeLayer(layerId);
        map.addLayer(definition);
    }

    function installSymbolLayers(map) {
        ensureMarkerImages(map);

        replaceLayerWithSymbol(map, 'pilgrim-points', {
            id: 'pilgrim-points',
            type: 'symbol',
            source: 'pilgrim-objects',
            filter: ['!', ['has', 'point_count']],
            layout: {
                'icon-image': [
                    'match', ['get', 'type_slug'],
                    'monastery', 'pm-monastery',
                    'chapel', 'pm-chapel',
                    'pm-temple',
                ],
                'icon-size': ['interpolate', ['linear'], ['zoom'], 10, 0.76, 14, 0.94, 18, 1.12],
                'icon-anchor': 'bottom',
                'icon-allow-overlap': false,
                'icon-ignore-placement': false,
                'icon-padding': 3,
            },
        });

        replaceLayerWithSymbol(map, 'points-of-interest', {
            id: 'points-of-interest',
            type: 'symbol',
            source: 'points-of-interest',
            layout: {
                'icon-image': [
                    'match', ['get', 'category'],
                    'parking', 'pm-parking',
                    'hotel', 'pm-hotel',
                    'cafe', 'pm-cafe',
                    'pm-attraction',
                ],
                'icon-size': ['interpolate', ['linear'], ['zoom'], 12, 0.72, 16, 0.92, 19, 1.05],
                'icon-anchor': 'bottom',
                'icon-allow-overlap': false,
                'icon-ignore-placement': false,
                'icon-padding': 2,
            },
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function focusIconClass(typeSlug) {
        if (typeSlug === 'monastery') return 'bi-buildings';
        if (typeSlug === 'chapel') return 'bi-house-heart';
        return 'bi-bank2';
    }

    function focusObjectUrl(slug) {
        return '/api/map/object-by-slug/' + encodeURIComponent(slug);
    }

    async function focusRequestedObject(map) {
        const params = new URLSearchParams(window.location.search);
        const slug = String(params.get('focus_slug') || '').trim();
        if (!slug) return;

        try {
            const response = await fetch(focusObjectUrl(slug), {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
            });
            const payload = await response.json();

            if (!response.ok || !payload.data) {
                throw new Error(payload.message || 'Объект не найден.');
            }

            const item = payload.data;
            const longitude = Number(item.longitude);
            const latitude = Number(item.latitude);
            if (!Number.isFinite(longitude) || !Number.isFinite(latitude)) return;

            const markerElement = document.createElement('button');
            markerElement.type = 'button';
            markerElement.className = 'focused-map-marker';
            markerElement.title = item.name || 'Выбранный объект';
            markerElement.setAttribute('aria-label', item.name || 'Выбранный объект');
            markerElement.innerHTML = '<span class="focused-map-marker__pulse"></span>'
                + '<span class="focused-map-marker__body"><i class="bi '
                + focusIconClass(item.type_slug)
                + '"></i></span>';

            const popupHtml = '<article class="map-popup">'
                + (item.cover ? '<img src="' + escapeHtml(item.cover) + '" alt="">' : '')
                + '<div class="map-popup-body">'
                + '<div class="small text-secondary mb-1">' + escapeHtml(item.type || 'Паломнический объект') + '</div>'
                + '<div class="fw-bold mb-2">' + escapeHtml(item.name) + '</div>'
                + '<div class="small text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i>' + escapeHtml(item.address || '') + '</div>'
                + '<a class="btn btn-sm btn-pm-green w-100" href="' + escapeHtml(item.url || '#') + '">Открыть карточку</a>'
                + '</div></article>';

            const popup = new window.maplibregl.Popup({offset: 34, maxWidth: '340px'})
                .setHTML(popupHtml);

            new window.maplibregl.Marker({element: markerElement, anchor: 'bottom'})
                .setLngLat([longitude, latitude])
                .setPopup(popup)
                .addTo(map);

            map.flyTo({
                center: [longitude, latitude],
                zoom: Math.min(18.5, map.getMaxZoom()),
                speed: 1.35,
                curve: 1.25,
                essential: true,
            });

            popup.setLngLat([longitude, latitude]).addTo(map);
        } catch (error) {
            console.error('[map-focus]', error);
        }
    }

    const mapPrototype = window.maplibregl.Map.prototype;
    const originalAddControl = mapPrototype.addControl;

    mapPrototype.addControl = function () {
        if (!this.__pilgrimEnhancementsBound) {
            this.__pilgrimEnhancementsBound = true;
            this.once('load', () => {
                window.setTimeout(() => installSymbolLayers(this), 0);
                focusRequestedObject(this);
            });
        }

        return originalAddControl.apply(this, arguments);
    };
})();
