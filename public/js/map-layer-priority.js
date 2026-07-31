(function () {
    'use strict';

    const primaryLayerIds = [
        'pilgrim-clusters',
        'pilgrim-cluster-count',
        'pilgrim-points',
        'pilgrim-point-icons'
    ];

    function promotePrimaryLayers(map) {
        primaryLayerIds.forEach((layerId) => {
            if (map.getLayer(layerId)) {
                map.moveLayer(layerId);
            }
        });
    }

    function patchMapLibre() {
        const prototype = window.maplibregl?.Map?.prototype;

        if (!prototype || prototype.__pilgrimLayerPriority) {
            return;
        }

        prototype.__pilgrimLayerPriority = true;
        const originalAddLayer = prototype.addLayer;

        prototype.addLayer = function (layer, beforeId) {
            const result = originalAddLayer.call(this, layer, beforeId);

            if (layer?.id === 'points-of-interest' || layer?.id === 'point-of-interest-icons') {
                queueMicrotask(() => promotePrimaryLayers(this));
            }

            return result;
        };
    }

    function bindMapLibreScript(script) {
        if (!script || script.dataset.layerPriorityBound === '1') {
            return;
        }

        script.dataset.layerPriorityBound = '1';
        script.addEventListener('load', patchMapLibre, {once: true});
    }

    if (window.maplibregl) {
        patchMapLibre();
        return;
    }

    document.querySelectorAll('script[src*="maplibre-gl.js"]').forEach(bindMapLibreScript);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node instanceof HTMLScriptElement && node.src.includes('maplibre-gl.js')) {
                bindMapLibreScript(node);
            }
        }));
    });

    observer.observe(document.documentElement, {childList: true, subtree: true});
})();
