(function () {
    'use strict';

    if (!window.maplibregl || !window.maplibregl.Map) {
        return;
    }

    const mapPrototype = window.maplibregl.Map.prototype;

    if (mapPrototype.__pilgrimViewportReadyPatchApplied) {
        return;
    }

    mapPrototype.__pilgrimViewportReadyPatchApplied = true;

    const originalLoaded = mapPrototype.loaded;
    const previousAddControl = mapPrototype.addControl;

    mapPrototype.loaded = function () {
        const container = typeof this.getContainer === 'function'
            ? this.getContainer()
            : null;

        if (container && container.id === 'pilgrim-map' && this.__pilgrimInitialLoadCompleted) {
            return true;
        }

        return originalLoaded.apply(this, arguments);
    };

    mapPrototype.addControl = function () {
        const container = typeof this.getContainer === 'function'
            ? this.getContainer()
            : null;

        if (
            container
            && container.id === 'pilgrim-map'
            && !this.__pilgrimInitialLoadListenerBound
        ) {
            this.__pilgrimInitialLoadListenerBound = true;
            this.once('load', () => {
                this.__pilgrimInitialLoadCompleted = true;
            });
        }

        return previousAddControl.apply(this, arguments);
    };
})();
