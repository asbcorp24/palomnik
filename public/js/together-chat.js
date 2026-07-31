(function () {
    const container = document.getElementById('discussionMessages');
    const discussion = document.getElementById('discussion');
    const form = discussion?.querySelector('form[action$="/messages"]');

    if (!container || !form) {
        return;
    }

    const feedUrl = window.location.pathname.replace(/\/$/, '') + '/messages-feed';
    const reportedUserInput = document.getElementById('reportedUserId');
    const defaultReportedUserId = reportedUserInput?.value || '';
    let firstLoad = true;
    let requestInProgress = false;

    function bindReportButtons() {
        document.querySelectorAll('.report-message-button').forEach((button) => {
            if (button.dataset.reportBound === '1') {
                return;
            }

            button.dataset.reportBound = '1';
            button.addEventListener('click', () => {
                const messageInput = document.getElementById('reportedMessageId');
                const userInput = document.getElementById('reportedUserId');

                if (messageInput) messageInput.value = button.dataset.messageId || '';
                if (userInput) userInput.value = button.dataset.userId || defaultReportedUserId;
            });
        });
    }

    async function refreshMessages() {
        if (requestInProgress || document.hidden) {
            return;
        }

        requestInProgress = true;
        const wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 90;

        try {
            const response = await fetch(feedUrl, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            if (html !== container.innerHTML) {
                container.innerHTML = html;
                bindReportButtons();

                if (firstLoad || wasNearBottom) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        } catch (error) {
            console.debug('Не удалось обновить обсуждение:', error);
        } finally {
            firstLoad = false;
            requestInProgress = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const textarea = form.querySelector('textarea[name="body"]');
        const submitButton = form.querySelector('button[type="submit"]');
        const body = textarea?.value.trim() || '';

        if (body.length < 2) {
            textarea?.focus();
            return;
        }

        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (response.status === 422) {
                const payload = await response.json();
                const firstError = Object.values(payload.errors || {}).flat()[0] || payload.message;
                alert(firstError || 'Проверьте текст сообщения.');
                return;
            }

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            if (textarea) textarea.value = '';
            await refreshMessages();
            container.scrollTop = container.scrollHeight;
        } catch (error) {
            console.error('Не удалось отправить сообщение:', error);
            alert('Не удалось отправить сообщение. Проверьте соединение и повторите попытку.');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });

    document.getElementById('reportModal')?.addEventListener('hidden.bs.modal', () => {
        const messageInput = document.getElementById('reportedMessageId');
        const userInput = document.getElementById('reportedUserId');
        if (messageInput) messageInput.value = '';
        if (userInput) userInput.value = defaultReportedUserId;
    });

    bindReportButtons();
    refreshMessages();
    window.setInterval(refreshMessages, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshMessages();
    });
})();

(function () {
    if (!document.getElementById('pilgrim-map')) {
        return;
    }

    function semanticObjectIcon(type) {
        const value = String(type || '').toLocaleLowerCase('ru-RU');

        if (value.includes('монастыр') || value.includes('monastery') || value.includes('monastyr')) return 'monastery';
        if (value.includes('часовн') || value.includes('chapel')) return 'chapel';
        if (value.includes('источник') || value.includes('купель') || value.includes('spring') || value.includes('source')) return 'spring';
        if (value.includes('храм') || value.includes('церков') || value.includes('собор') || value.includes('temple') || value.includes('church') || value.includes('cathedral')) return 'temple';

        return 'landmark';
    }

    function createMapIcon(iconName) {
        const size = 48;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const context = canvas.getContext('2d');

        context.clearRect(0, 0, size, size);
        context.strokeStyle = '#ffffff';
        context.fillStyle = '#ffffff';
        context.lineWidth = 4;
        context.lineCap = 'round';
        context.lineJoin = 'round';

        const line = (...points) => {
            context.beginPath();
            context.moveTo(points[0], points[1]);
            for (let index = 2; index < points.length; index += 2) {
                context.lineTo(points[index], points[index + 1]);
            }
            context.stroke();
        };
        const cross = (x, y, scale) => {
            line(x, y - (7 * scale), x, y + (7 * scale));
            line(x - (5 * scale), y - (2 * scale), x + (5 * scale), y - (2 * scale));
        };
        const dome = (x, y, radius) => {
            context.beginPath();
            context.arc(x, y, radius, Math.PI, 0);
            context.stroke();
        };

        switch (iconName) {
            case 'temple':
                cross(24, 11, .85);
                dome(24, 25, 8);
                line(16, 25, 16, 38, 32, 38, 32, 25);
                break;
            case 'monastery':
                cross(24, 8, .65);
                dome(24, 18, 6);
                line(18, 18, 18, 37, 30, 37, 30, 18);
                dome(10, 26, 4);
                dome(38, 26, 4);
                line(6, 26, 6, 39, 42, 39, 42, 26);
                break;
            case 'chapel':
                cross(24, 12, .65);
                line(13, 26, 24, 18, 35, 26, 35, 39, 13, 39, 13, 26);
                line(24, 30, 24, 39);
                break;
            case 'spring':
                context.beginPath();
                context.moveTo(24, 7);
                context.bezierCurveTo(18, 18, 13, 25, 13, 31);
                context.bezierCurveTo(13, 39, 18, 43, 24, 43);
                context.bezierCurveTo(30, 43, 35, 39, 35, 31);
                context.bezierCurveTo(35, 25, 30, 18, 24, 7);
                context.stroke();
                break;
            case 'parking':
                context.font = '700 31px Arial, sans-serif';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText('P', 24, 25);
                break;
            case 'cafe':
                context.beginPath();
                context.rect(10, 19, 24, 15);
                context.stroke();
                context.beginPath();
                context.arc(36, 25, 7, -Math.PI / 2, Math.PI / 2);
                context.stroke();
                line(9, 39, 39, 39);
                line(15, 13, 15, 8);
                line(23, 13, 23, 8);
                break;
            case 'hotel':
                line(8, 13, 8, 39);
                line(8, 33, 41, 33);
                line(41, 24, 41, 39);
                context.beginPath();
                context.rect(13, 22, 25, 11);
                context.stroke();
                context.beginPath();
                context.arc(17, 18, 4, 0, Math.PI * 2);
                context.fill();
                break;
            case 'attraction':
                context.beginPath();
                for (let index = 0; index < 10; index++) {
                    const radius = index % 2 === 0 ? 17 : 7;
                    const angle = (-Math.PI / 2) + (index * Math.PI / 5);
                    const x = 24 + Math.cos(angle) * radius;
                    const y = 24 + Math.sin(angle) * radius;
                    index ? context.lineTo(x, y) : context.moveTo(x, y);
                }
                context.closePath();
                context.fill();
                break;
            default:
                context.beginPath();
                context.arc(24, 19, 11, 0, Math.PI * 2);
                context.stroke();
                context.beginPath();
                context.arc(24, 19, 3, 0, Math.PI * 2);
                context.fill();
                line(18, 29, 24, 42, 30, 29);
        }

        return context.getImageData(0, 0, size, size);
    }

    function patchMapLibre() {
        const mapLibrary = window.maplibregl;
        const prototype = mapLibrary?.Map?.prototype;

        if (!prototype || prototype.__pilgrimSemanticIcons) {
            return;
        }

        prototype.__pilgrimSemanticIcons = true;
        const originalAddSource = prototype.addSource;
        const originalAddLayer = prototype.addLayer;
        const originalSetFilter = prototype.setFilter;

        function enrichGeoJson(source, resolver) {
            if (!source || source.type !== 'geojson' || !source.data || !Array.isArray(source.data.features)) {
                return source;
            }

            return {
                ...source,
                data: {
                    ...source.data,
                    features: source.data.features.map((feature) => ({
                        ...feature,
                        properties: {
                            ...(feature.properties || {}),
                            marker_icon: resolver(feature.properties || {})
                        }
                    }))
                }
            };
        }

        function registerIcons(map) {
            ['temple', 'monastery', 'chapel', 'spring', 'landmark', 'parking', 'cafe', 'hotel', 'attraction']
                .forEach((iconName) => {
                    const imageName = `pilgrim-${iconName}`;
                    if (!map.hasImage(imageName)) {
                        map.addImage(imageName, createMapIcon(iconName), {pixelRatio: 2});
                    }
                });
        }

        prototype.addSource = function (id, source) {
            if (id === 'pilgrim-objects') {
                source = enrichGeoJson(source, (properties) => semanticObjectIcon(properties.type));
            } else if (id === 'points-of-interest') {
                source = enrichGeoJson(source, (properties) => ['parking', 'cafe', 'hotel', 'attraction'].includes(properties.category)
                    ? properties.category
                    : 'attraction');
            }

            return originalAddSource.call(this, id, source);
        };

        prototype.addLayer = function (layer, beforeId) {
            if (layer?.id === 'pilgrim-points') {
                registerIcons(this);
                const result = originalAddLayer.call(this, {
                    ...layer,
                    paint: {...(layer.paint || {}), 'circle-radius': 13}
                }, beforeId);

                if (!this.getLayer('pilgrim-point-icons')) {
                    originalAddLayer.call(this, {
                        id: 'pilgrim-point-icons',
                        type: 'symbol',
                        source: 'pilgrim-objects',
                        filter: ['!', ['has', 'point_count']],
                        layout: {
                            'icon-image': ['concat', 'pilgrim-', ['coalesce', ['get', 'marker_icon'], 'landmark']],
                            'icon-size': 1,
                            'icon-allow-overlap': true,
                            'icon-ignore-placement': true
                        }
                    }, beforeId);
                }

                return result;
            }

            if (layer?.id === 'points-of-interest') {
                registerIcons(this);
                const result = originalAddLayer.call(this, {
                    ...layer,
                    paint: {...(layer.paint || {}), 'circle-radius': 11}
                }, beforeId);

                if (!this.getLayer('point-of-interest-icons')) {
                    originalAddLayer.call(this, {
                        id: 'point-of-interest-icons',
                        type: 'symbol',
                        source: 'points-of-interest',
                        layout: {
                            'icon-image': ['concat', 'pilgrim-', ['coalesce', ['get', 'marker_icon'], 'attraction']],
                            'icon-size': .86,
                            'icon-allow-overlap': true,
                            'icon-ignore-placement': true
                        }
                    }, beforeId);
                }

                [
                    'pilgrim-clusters',
                    'pilgrim-cluster-count',
                    'pilgrim-points',
                    'pilgrim-point-icons'
                ].forEach((primaryLayerId) => {
                    if (this.getLayer(primaryLayerId)) {
                        this.moveLayer(primaryLayerId);
                    }
                });

                return result;
            }

            return originalAddLayer.call(this, layer, beforeId);
        };

        prototype.setFilter = function (layerId, filter, options) {
            const result = originalSetFilter.call(this, layerId, filter, options);

            if (layerId === 'points-of-interest' && this.getLayer('point-of-interest-icons')) {
                originalSetFilter.call(this, 'point-of-interest-icons', filter, options);
            }

            return result;
        };
    }

    function bindMapLibreScript(script) {
        if (!script || script.dataset.semanticIconsBound === '1') {
            return;
        }
        script.dataset.semanticIconsBound = '1';
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
                observer.disconnect();
            }
        }));
    });
    observer.observe(document.documentElement, {childList: true, subtree: true});
})();

(function () {
    const objectSidebar = document.querySelector('section.section-space aside.col-lg-4 > .d-grid.position-sticky');
    const objectTitle = document.querySelector('.page-hero .section-title');

    if (!objectSidebar || !objectTitle || document.querySelector('script[data-object-mini-map-loader]')) {
        return;
    }

    const currentScriptUrl = new URL(document.currentScript?.src || window.location.href, window.location.origin);
    const applicationPath = currentScriptUrl.pathname.replace(/\/js\/together-chat\.js$/, '');
    const script = document.createElement('script');
    script.src = `${currentScriptUrl.origin}${applicationPath}/js/object-mini-map.js`;
    script.dataset.objectMiniMapLoader = '1';
    document.head.appendChild(script);
})();
