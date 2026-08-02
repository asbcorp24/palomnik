(function () {
    'use strict';

    if (!window.maplibregl || !window.maplibregl.Map) {
        return;
    }

    const LAYER_ID = 'pilgrim-points';
    const LEGACY_LAYER_ID = 'pilgrim-religious-icons';
    const FALLBACK_LAYER_ID = 'pilgrim-religious-fallback';
    const SOURCE_ID = 'pilgrim-objects';
    const IMAGES = {
        temple: 'pm3-temple',
        monastery: 'pm3-monastery',
        chapel: 'pm3-chapel',
    };

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
        const definitions = [
            [IMAGES.temple, 'temple', '#9b6a19'],
            [IMAGES.monastery, 'monastery', '#26443b'],
            [IMAGES.chapel, 'chapel', '#795548'],
        ];

        definitions.forEach(function (definition) {
            if (!map.hasImage(definition[0])) {
                map.addImage(definition[0], markerImage(definition[1], definition[2]), {pixelRatio: 2});
            }
        });
    }

    function installLayers(map) {
        if (!map.getSource(SOURCE_ID)) {
            return false;
        }

        ensureImages(map);

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

        if (map.getLayer(LEGACY_LAYER_ID)) {
            map.removeLayer(LEGACY_LAYER_ID);
        }

        if (map.getLayer(LAYER_ID)) {
            map.removeLayer(LAYER_ID);
        }

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

        map.moveLayer(FALLBACK_LAYER_ID);
        map.moveLayer(LAYER_ID);
        return true;
    }

    function installWhenReady(map) {
        let attempts = 0;
        const tryInstall = function () {
            attempts += 1;
            try {
                if (installLayers(map) || attempts >= 50) {
                    return;
                }
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
