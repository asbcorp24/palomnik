(function () {
    'use strict';

    const imageSelector = [
        'img.object-cover',
        'img.detail-cover',
        'img.gallery-image',
        '.info-card > img.offline-asset'
    ].join(',');

    const imageExtension = /\.(?:avif|bmp|gif|jpe?g|png|svg|webp)(?:$|[?#])/i;
    const items = [];
    let activeItems = [];
    let activeIndex = 0;
    let lastFocused = null;
    let loadSequence = 0;
    let touchStartX = null;
    let touchStartY = null;
    let previousBodyPaddingRight = '';

    function createIcon(path) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + path + '"></path></svg>';
    }

    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'pm-lightbox';
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Увеличенный просмотр фотографии');
        overlay.innerHTML = [
            '<div class="pm-lightbox__stage">',
            '  <button class="pm-lightbox__button pm-lightbox__close" type="button" aria-label="Закрыть увеличенное фото">',
            createIcon('M6 6l12 12M18 6L6 18'),
            '  </button>',
            '  <button class="pm-lightbox__button pm-lightbox__previous" type="button" aria-label="Предыдущая фотография">',
            createIcon('M15 18l-6-6 6-6'),
            '  </button>',
            '  <figure class="pm-lightbox__figure">',
            '    <div class="pm-lightbox__image-wrap">',
            '      <span class="pm-lightbox__loader" aria-label="Фотография загружается"></span>',
            '      <div class="pm-lightbox__error" hidden>Не удалось загрузить фотографию.</div>',
            '      <img class="pm-lightbox__image" alt="">',
            '    </div>',
            '    <figcaption class="pm-lightbox__caption"></figcaption>',
            '  </figure>',
            '  <button class="pm-lightbox__button pm-lightbox__next" type="button" aria-label="Следующая фотография">',
            createIcon('M9 18l6-6-6-6'),
            '  </button>',
            '  <div class="pm-lightbox__counter" aria-live="polite"></div>',
            '</div>'
        ].join('');

        document.body.appendChild(overlay);
        return overlay;
    }

    function fullSizeSource(image, trigger) {
        const href = trigger instanceof HTMLAnchorElement ? trigger.href : '';
        if (href && imageExtension.test(href)) {
            return href;
        }

        return image.currentSrc || image.src || image.getAttribute('src') || '';
    }

    function captionFor(image) {
        const explicitCaption = image.dataset.lightboxCaption || image.getAttribute('title');
        if (explicitCaption) {
            return explicitCaption.trim();
        }

        const alt = image.getAttribute('alt');
        if (alt) {
            return alt.trim();
        }

        const cardTitle = image.closest('.card-pm')?.querySelector('.object-title')?.textContent;
        return cardTitle ? cardTitle.trim() : 'Фотография паломнического объекта';
    }

    function groupFor(image) {
        if (image.dataset.lightboxGroup) {
            return image.dataset.lightboxGroup;
        }

        return image.classList.contains('object-cover') ? 'object-catalog' : 'object-detail';
    }

    function prepareItems() {
        document.querySelectorAll(imageSelector).forEach((image) => {
            const parentLink = image.closest('a');
            const trigger = parentLink || image;

            if (trigger.dataset.pmLightboxBound === '1') {
                return;
            }

            const source = fullSizeSource(image, trigger);
            if (!source) {
                return;
            }

            const item = {
                image,
                trigger,
                source,
                caption: captionFor(image),
                group: groupFor(image)
            };

            trigger.dataset.pmLightboxBound = '1';
            trigger.classList.add('pm-lightbox-trigger');
            image.classList.add('pm-lightbox-image');

            if (!(trigger instanceof HTMLAnchorElement)) {
                trigger.setAttribute('role', 'button');
                trigger.setAttribute('tabindex', '0');
            }

            trigger.setAttribute('aria-label', 'Увеличить фотографию: ' + item.caption);

            trigger.addEventListener('click', (event) => {
                if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();
                openItem(item);
            });

            trigger.addEventListener('keydown', (event) => {
                if ((event.key === 'Enter' || event.key === ' ') && !(trigger instanceof HTMLAnchorElement && event.key === 'Enter')) {
                    event.preventDefault();
                    openItem(item);
                }
            });

            items.push(item);
        });
    }

    const overlay = createOverlay();
    const stage = overlay.querySelector('.pm-lightbox__stage');
    const displayedImage = overlay.querySelector('.pm-lightbox__image');
    const caption = overlay.querySelector('.pm-lightbox__caption');
    const counter = overlay.querySelector('.pm-lightbox__counter');
    const loader = overlay.querySelector('.pm-lightbox__loader');
    const errorMessage = overlay.querySelector('.pm-lightbox__error');
    const closeButton = overlay.querySelector('.pm-lightbox__close');
    const previousButton = overlay.querySelector('.pm-lightbox__previous');
    const nextButton = overlay.querySelector('.pm-lightbox__next');

    function openItem(item) {
        activeItems = items.filter((candidate) => candidate.group === item.group);
        activeIndex = Math.max(0, activeItems.indexOf(item));
        lastFocused = document.activeElement;

        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        previousBodyPaddingRight = document.body.style.paddingRight;
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = scrollbarWidth + 'px';
        }

        document.body.classList.add('pm-lightbox-open');
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        renderActiveItem();

        window.requestAnimationFrame(() => closeButton.focus());
    }

    function closeLightbox() {
        if (overlay.hidden) {
            return;
        }

        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        displayedImage.removeAttribute('src');
        displayedImage.classList.remove('is-loaded');
        document.body.classList.remove('pm-lightbox-open');
        document.body.style.paddingRight = previousBodyPaddingRight;

        if (lastFocused instanceof HTMLElement) {
            lastFocused.focus({preventScroll: true});
        }
    }

    function renderActiveItem() {
        const item = activeItems[activeIndex];
        if (!item) {
            closeLightbox();
            return;
        }

        const currentLoad = ++loadSequence;
        displayedImage.classList.remove('is-loaded');
        displayedImage.alt = item.caption;
        caption.textContent = item.caption;
        loader.hidden = false;
        errorMessage.hidden = true;

        const hasNavigation = activeItems.length > 1;
        previousButton.hidden = !hasNavigation;
        nextButton.hidden = !hasNavigation;
        counter.hidden = !hasNavigation;
        counter.textContent = hasNavigation ? (activeIndex + 1) + ' / ' + activeItems.length : '';

        displayedImage.onload = () => {
            if (currentLoad !== loadSequence) {
                return;
            }

            loader.hidden = true;
            displayedImage.classList.add('is-loaded');
        };

        displayedImage.onerror = () => {
            if (currentLoad !== loadSequence) {
                return;
            }

            loader.hidden = true;
            errorMessage.hidden = false;
        };

        displayedImage.src = item.source;

        if (displayedImage.complete && displayedImage.naturalWidth > 0) {
            displayedImage.onload();
        }

        preloadNeighbour(-1);
        preloadNeighbour(1);
    }

    function preloadNeighbour(offset) {
        if (activeItems.length < 2) {
            return;
        }

        const index = (activeIndex + offset + activeItems.length) % activeItems.length;
        const preloader = new Image();
        preloader.src = activeItems[index].source;
    }

    function move(offset) {
        if (activeItems.length < 2) {
            return;
        }

        activeIndex = (activeIndex + offset + activeItems.length) % activeItems.length;
        renderActiveItem();
    }

    closeButton.addEventListener('click', closeLightbox);
    previousButton.addEventListener('click', () => move(-1));
    nextButton.addEventListener('click', () => move(1));

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay || event.target === stage || event.target.classList.contains('pm-lightbox__image-wrap')) {
            closeLightbox();
        }
    });

    stage.addEventListener('touchstart', (event) => {
        const touch = event.changedTouches[0];
        touchStartX = touch?.clientX ?? null;
        touchStartY = touch?.clientY ?? null;
    }, {passive: true});

    stage.addEventListener('touchend', (event) => {
        if (touchStartX === null || touchStartY === null) {
            return;
        }

        const touch = event.changedTouches[0];
        const deltaX = (touch?.clientX ?? touchStartX) - touchStartX;
        const deltaY = (touch?.clientY ?? touchStartY) - touchStartY;
        touchStartX = null;
        touchStartY = null;

        if (Math.abs(deltaX) >= 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
            move(deltaX < 0 ? 1 : -1);
        }
    }, {passive: true});

    document.addEventListener('keydown', (event) => {
        if (overlay.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            move(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'Tab') {
            const focusable = [closeButton, previousButton, nextButton].filter((button) => !button.hidden);
            if (!focusable.length) {
                return;
            }

            const currentIndex = focusable.indexOf(document.activeElement);
            const nextIndex = event.shiftKey
                ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
                : (currentIndex + 1) % focusable.length;
            event.preventDefault();
            focusable[nextIndex].focus();
        }
    });

    prepareItems();
})();
