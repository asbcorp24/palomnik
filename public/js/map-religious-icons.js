(function () {
    'use strict';

    if (!window.maplibregl || !window.maplibregl.Map) {
        return;
    }

    const ICONS = {
        temple: {
            name: 'pm2-temple',
            color: '#9b6a19',
        },
        monastery: {
            name: 'pm2-monastery',
            color: '#26443b',
        },
        chapel: {
            name: 'pm2-chapel',
            color: '#795548',
        },
    };

    function pinPath(color) {
        return '<path d="M36 3C18 3 6 16 6 34c0 20 21 38 30 47 9-9 30-27 30-47C66 16 54 3 36 3Z" '
            + 'fill="' + color + '" stroke="#fffdf9" stroke-width="5" stroke-linejoin="round"/>';
    }

    function cross(x, top, height, width) {
        const center = x;
        const armY = top + Math.round(height * 0.38);
        return '<rect x="' + (center - width / 2) + '" y="' + top + '" width="' + width + '" height="' + height + '" rx="1" fill="#fff"/>'
            + '<rect x="' + (center - height * 0.24) + '" y="' + armY + '" width="' + (height * 0.48) + '" height="' + width + '" rx="1" fill="#fff"/>';
    }

    function templeSvg(color) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="72" height="86" viewBox="0 0 72 86">'
            + pinPath(color)
            + '<g>'
            + cross(36, 10, 13, 3)
            + '<path d="M36 20c-5 3-9 8-9 14h18c0-6-4-11-9-14Z" fill="#fff"/>'
            + '<rect x="31" y="32" width="10" height="8" rx="1.5" fill="#fff"/>'
            + '<path d="M21 42h30v14H21z" fill="#fff"/>'
            + '<path d="M17 43 24 36h24l7 7Z" fill="#fff"/>'
            + '<rect x="33" y="47" width="6" height="9" rx="3" fill="' + color + '"/>'
            + '</g></svg>';
    }

    function monasterySvg(color) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="72" height="86" viewBox="0 0 72 86">'
            + pinPath(color)
            + '<g fill="#fff">'
            + '<path d="M14 42h44v14H14z"/>'
            + '<path d="M16 34h12v22H16zM44 34h12v22H44z"/>'
            + '<path d="M14 34 22 27l8 7ZM42 34l8-7 8 7Z"/>'
            + '<path d="M29 38h14v18H29z"/>'
            + '<path d="M36 22c-4 3-7 7-7 12h14c0-5-3-9-7-12Z"/>'
            + '<rect x="33" y="32" width="6" height="8" rx="1"/>'
            + cross(36, 13, 11, 2.5)
            + '<rect x="19" y="44" width="5" height="7" rx="2.5" fill="' + color + '"/>'
            + '<rect x="48" y="44" width="5" height="7" rx="2.5" fill="' + color + '"/>'
            + '<rect x="33" y="47" width="6" height="9" rx="3" fill="' + color + '"/>'
            + '</g></svg>';
    }

    function chapelSvg(color) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="72" height="86" viewBox="0 0 72 86">'
            + pinPath(color)
            + '<g fill="#fff">'
            + cross(36, 14, 13, 3)
            + '<path d="M20 39 36 25l16 14Z"/>'
            + '<rect x="24" y="38" width="24" height="18" rx="2"/>'
            + '<rect x="33" y="46" width="6" height="10" rx="3" fill="' + color + '"/>'
            + '</g></svg>';
    }

    function svgForKind(kind, color) {
        if (kind === 'monastery') {
            return monasterySvg(color);
        }
        if (kind === 'chapel') {
            return chapelSvg(color);
        }
        return templeSvg(color);
    }

    function loadSvgImage(svg) {
        return new Promise(function (resolve, reject) {
            const image = new Image();
            image.onload = function () {
                resolve(image);
            };
            image.onerror = function () {
                reject(new Error('Не удалось загрузить SVG-маркер.'));
            };
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        });
    }

    async function ensureImages(map) {
        const entries = Object.entries(ICONS);

        for (const entry of entries) {
            const kind = entry[0];
            const definition = entry[1];

            if (map.hasImage(definition.name)) {
                continue;
            }

            const image = await loadSvgImage(svgForKind(kind, definition.color));
            map.addImage(definition.name, image, {pixelRatio: 2});
        }
    }

    function forceMarkerVisibility(map) {
        if (!map.getLayer('pilgrim-points')) {
            return;
        }

        map.setLayoutProperty('pilgrim-points', 'visibility', 'visible');
        map.setLayoutProperty('pilgrim-points', 'icon-allow-overlap', true);
        map.setLayoutProperty('pilgrim-points', 'icon-ignore-placement', true);
        map.setLayoutProperty('pilgrim-points', 'icon-padding', 0);
    }

    async function applyReligiousIcons(map) {
        forceMarkerVisibility(map);

        try {
            await ensureImages(map);

            if (!map.getLayer('pilgrim-points')) {
                return;
            }

            map.setLayoutProperty('pilgrim-points', 'icon-image', [
                'match',
                ['get', 'type_slug'],
                'monastery', ICONS.monastery.name,
                'chapel', ICONS.chapel.name,
                'church', ICONS.temple.name,
                'cathedral', ICONS.temple.name,
                'temple', ICONS.temple.name,
                ICONS.temple.name,
            ]);
            map.setLayoutProperty('pilgrim-points', 'icon-size', [
                'interpolate', ['linear'], ['zoom'],
                10, 0.84,
                14, 1.02,
                18, 1.20,
            ]);
            forceMarkerVisibility(map);
        } catch (error) {
            forceMarkerVisibility(map);
            console.error('[map-religious-icons]', error);
        }
    }

    function inlineSymbol(kind) {
        if (kind === 'monastery') {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="25" height="25" aria-hidden="true" style="transform:rotate(45deg)">'
                + '<g fill="currentColor"><path d="M2 18h28v11H2zM4 12h7v17H4zM21 12h7v17h-7zM3 12l4.5-5L12 12h-9zm17 0 4.5-5 4.5 5h-9zM12 15h8v14h-8zM16 7c-3 2-4 4-4 7h8c0-3-1-5-4-7z"/>'
                + '<path d="M15 1h2v7h-2zM12.5 3.5h7v2h-7z"/></g></svg>';
        }

        if (kind === 'chapel') {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="25" height="25" aria-hidden="true" style="transform:rotate(45deg)">'
                + '<g fill="currentColor"><path d="M4 17 16 7l12 10H4zM7 16h18v14H7z"/><path d="M15 1h2v8h-2zM12 4h8v2h-8z"/></g></svg>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="25" height="25" aria-hidden="true" style="transform:rotate(45deg)">'
            + '<g fill="currentColor"><path d="M5 18h22v12H5zM16 8c-4 2-6 5-6 9h12c0-4-2-7-6-9zM13 16h6v5h-6z"/><path d="M15 1h2v8h-2zM12 4h8v2h-8z"/></g></svg>';
    }

    async function improveFocusedMarker() {
        const slug = String(new URLSearchParams(window.location.search).get('focus_slug') || '').trim();
        if (!slug) {
            return;
        }

        try {
            const response = await fetch('/api/map/object-by-slug/' + encodeURIComponent(slug), {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const payload = await response.json();
            const kind = payload && payload.data ? String(payload.data.type_slug || '') : '';

            for (let attempt = 0; attempt < 30; attempt++) {
                const body = document.querySelector('.focused-map-marker__body');
                if (body) {
                    body.innerHTML = inlineSymbol(kind);
                    return;
                }
                await new Promise(function (resolve) {
                    window.setTimeout(resolve, 100);
                });
            }
        } catch (error) {
            console.error('[focused-religious-icon]', error);
        }
    }

    const mapPrototype = window.maplibregl.Map.prototype;
    const previousAddControl = mapPrototype.addControl;

    mapPrototype.addControl = function () {
        if (!this.__pilgrimReligiousIconsBound) {
            this.__pilgrimReligiousIconsBound = true;
            const map = this;

            map.once('load', function () {
                window.setTimeout(function () {
                    applyReligiousIcons(map);
                    improveFocusedMarker();
                }, 120);
            });
        }

        return previousAddControl.apply(this, arguments);
    };
})();
