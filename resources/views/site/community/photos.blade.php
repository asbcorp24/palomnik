@extends('site.layouts.app')

@section('title', 'Паломнические фотографии — Московский паломник')
@section('meta_description', 'Проверенные фотографии паломников, привязанные к маршрутам Московского паломника.')

@push('styles')
<style>
    .pilgrimage-photo-link {
        position: relative;
        display: block;
        overflow: hidden;
        background: #ece7df;
        cursor: zoom-in;
    }
    .pilgrimage-photo-link img {
        transition: transform .28s ease, filter .28s ease;
    }
    .pilgrimage-photo-link:hover img,
    .pilgrimage-photo-link:focus-visible img {
        transform: scale(1.035);
        filter: brightness(.84);
    }
    .pilgrimage-photo-zoom {
        position: absolute;
        right: 12px;
        bottom: 12px;
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255,255,255,.45);
        border-radius: 50%;
        color: #fff;
        background: rgba(20,31,27,.68);
        box-shadow: 0 8px 24px rgba(0,0,0,.2);
        backdrop-filter: blur(8px);
        opacity: 0;
        transform: translateY(7px);
        transition: opacity .22s ease, transform .22s ease;
        pointer-events: none;
    }
    .pilgrimage-photo-link:hover .pilgrimage-photo-zoom,
    .pilgrimage-photo-link:focus-visible .pilgrimage-photo-zoom {
        opacity: 1;
        transform: translateY(0);
    }

    body.pilgrimage-lightbox-open {
        overflow: hidden;
    }
    .pilgrimage-lightbox[hidden] {
        display: none !important;
    }
    .pilgrimage-lightbox {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px 84px;
        background: rgba(8,13,11,.94);
        opacity: 0;
        transition: opacity .18s ease;
    }
    .pilgrimage-lightbox.is-open {
        opacity: 1;
    }
    .pilgrimage-lightbox-dialog {
        position: relative;
        width: min(1280px, 100%);
        max-height: calc(100vh - 56px);
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 340px);
        overflow: hidden;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 22px;
        background: #111814;
        box-shadow: 0 34px 90px rgba(0,0,0,.5);
        transform: translateY(12px) scale(.985);
        transition: transform .18s ease;
    }
    .pilgrimage-lightbox.is-open .pilgrimage-lightbox-dialog {
        transform: translateY(0) scale(1);
    }
    .pilgrimage-lightbox-stage {
        position: relative;
        min-height: 420px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #050806;
        touch-action: pan-y;
    }
    .pilgrimage-lightbox-image {
        display: block;
        max-width: 100%;
        max-height: calc(100vh - 56px);
        object-fit: contain;
        user-select: none;
        -webkit-user-drag: none;
        opacity: 1;
        transition: opacity .14s ease;
    }
    .pilgrimage-lightbox-image.is-loading {
        opacity: .25;
    }
    .pilgrimage-lightbox-spinner {
        position: absolute;
        width: 48px;
        height: 48px;
        border: 4px solid rgba(255,255,255,.18);
        border-top-color: #fff;
        border-radius: 50%;
        animation: pilgrimage-lightbox-spin .75s linear infinite;
        pointer-events: none;
    }
    .pilgrimage-lightbox-spinner[hidden] {
        display: none;
    }
    @keyframes pilgrimage-lightbox-spin { to { transform: rotate(360deg); } }

    .pilgrimage-lightbox-info {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 34px 28px 26px;
        overflow-y: auto;
        color: rgba(255,255,255,.78);
        background: linear-gradient(165deg, #1c2924, #101713);
    }
    .pilgrimage-lightbox-title {
        margin: 0;
        color: #fff;
        font-family: Prata, Georgia, serif;
        font-size: clamp(1.25rem, 2vw, 1.75rem);
        line-height: 1.3;
    }
    .pilgrimage-lightbox-route {
        color: #e4c97c;
        text-decoration: none;
        font-weight: 600;
    }
    .pilgrimage-lightbox-route:hover {
        color: #f4dda0;
        text-decoration: underline;
    }
    .pilgrimage-lightbox-description {
        margin: 0;
        line-height: 1.65;
        white-space: pre-line;
    }
    .pilgrimage-lightbox-author {
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid rgba(255,255,255,.1);
        font-size: .88rem;
    }
    .pilgrimage-lightbox-counter {
        color: rgba(255,255,255,.52);
        font-size: .82rem;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .pilgrimage-lightbox-button {
        position: absolute;
        z-index: 2;
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 50%;
        color: #fff;
        background: rgba(12,20,17,.72);
        box-shadow: 0 8px 28px rgba(0,0,0,.28);
        backdrop-filter: blur(10px);
        transition: background .18s ease, transform .18s ease;
    }
    .pilgrimage-lightbox-button:hover,
    .pilgrimage-lightbox-button:focus-visible {
        color: #fff;
        background: rgba(176,138,62,.95);
        transform: scale(1.06);
    }
    .pilgrimage-lightbox-close {
        top: 18px;
        right: 18px;
    }
    .pilgrimage-lightbox-prev,
    .pilgrimage-lightbox-next {
        top: 50%;
        transform: translateY(-50%);
    }
    .pilgrimage-lightbox-prev:hover,
    .pilgrimage-lightbox-prev:focus-visible,
    .pilgrimage-lightbox-next:hover,
    .pilgrimage-lightbox-next:focus-visible {
        transform: translateY(-50%) scale(1.06);
    }
    .pilgrimage-lightbox-prev { left: 18px; }
    .pilgrimage-lightbox-next { right: 18px; }

    @media (max-width: 991.98px) {
        .pilgrimage-lightbox {
            padding: 16px;
        }
        .pilgrimage-lightbox-dialog {
            max-height: calc(100vh - 32px);
            grid-template-columns: 1fr;
            grid-template-rows: minmax(0, 1fr) auto;
            border-radius: 16px;
        }
        .pilgrimage-lightbox-stage {
            min-height: 280px;
        }
        .pilgrimage-lightbox-image {
            max-height: 68vh;
        }
        .pilgrimage-lightbox-info {
            max-height: 28vh;
            padding: 18px 20px 20px;
        }
        .pilgrimage-lightbox-author {
            margin-top: 0;
        }
    }
    @media (max-width: 575.98px) {
        .pilgrimage-photo-zoom {
            opacity: 1;
            transform: none;
        }
        .pilgrimage-lightbox {
            padding: 0;
        }
        .pilgrimage-lightbox-dialog {
            width: 100%;
            height: 100%;
            max-height: none;
            border: 0;
            border-radius: 0;
        }
        .pilgrimage-lightbox-stage {
            min-height: 0;
        }
        .pilgrimage-lightbox-image {
            max-height: 72vh;
        }
        .pilgrimage-lightbox-info {
            max-height: 28vh;
        }
        .pilgrimage-lightbox-close {
            top: 12px;
            right: 12px;
        }
        .pilgrimage-lightbox-prev { left: 10px; }
        .pilgrimage-lightbox-next { right: 10px; }
        .pilgrimage-lightbox-button {
            width: 44px;
            height: 44px;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .pilgrimage-photo-link img,
        .pilgrimage-photo-zoom,
        .pilgrimage-lightbox,
        .pilgrimage-lightbox-dialog,
        .pilgrimage-lightbox-image,
        .pilgrimage-lightbox-button {
            transition: none !important;
        }
    }
</style>
@endpush

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Общая фотолетопись</div>
                <h1 class="section-title mb-3">Паломнические фотографии</h1>
                <p class="section-lead mb-0">Снимки участников, прошедшие проверку модератора и привязанные к паломническим маршрутам.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @auth<a class="btn btn-pm-gold" href="{{ route('profile.photos') }}"><i class="bi bi-camera me-2"></i>Добавить свои фото</a>@endauth
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-4" method="GET" action="{{ route('community.photos') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label" for="route">Маршрут</label>
                    <select class="form-select" id="route" name="route">
                        <option value="">Все маршруты</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->slug }}" @selected(($filters['route'] ?? '') === $route->slug)>{{ $route->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-grid"><button class="btn btn-pm-green" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button></div>
            </div>
        </form>

        <div class="row g-4" id="pilgrimagePhotoGallery">
            @forelse($photos as $photo)
                @php
                    $photoTitle = $photo->title ?: optional($photo->pilgrimageRoute)->title ?: 'Паломническая фотография';
                    $photoRouteTitle = optional($photo->pilgrimageRoute)->title;
                    $photoRouteUrl = $photo->pilgrimageRoute ? route('routes.show', $photo->pilgrimageRoute) : '';
                    $photoAuthor = optional($photo->user)->name ?: 'Паломник';
                @endphp
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <article class="card-pm h-100 overflow-hidden">
                        <a
                            class="pilgrimage-photo-link"
                            href="{{ $photo->url }}"
                            data-pilgrimage-lightbox
                            data-title="{{ $photoTitle }}"
                            data-description="{{ $photo->description }}"
                            data-route-title="{{ $photoRouteTitle }}"
                            data-route-url="{{ $photoRouteUrl }}"
                            data-author="{{ $photoAuthor }}"
                            aria-label="Открыть фотографию: {{ $photoTitle }}"
                        >
                            <img class="w-100" src="{{ $photo->url }}" alt="{{ $photoTitle }}" style="height:260px;object-fit:cover" loading="lazy">
                            <span class="pilgrimage-photo-zoom" aria-hidden="true"><i class="bi bi-arrows-fullscreen"></i></span>
                        </a>
                        <div class="p-3">
                            <h2 class="h6 mb-2">{{ $photoTitle }}</h2>
                            @if($photo->pilgrimageRoute)<a class="small text-decoration-none d-block mb-2" href="{{ $photoRouteUrl }}"><i class="bi bi-signpost-split me-1"></i>{{ $photoRouteTitle }}</a>@endif
                            @if($photo->pilgrimageObject)<a class="small text-secondary text-decoration-none d-block mb-2" href="{{ route('objects.show', $photo->pilgrimageObject) }}"><i class="bi bi-geo-alt me-1"></i>{{ $photo->pilgrimageObject->name }}</a>@endif
                            @if($photo->description)<p class="small text-secondary mb-2">{{ \Illuminate\Support\Str::limit($photo->description, 130) }}</p>@endif
                            <div class="small text-secondary">Фото: {{ $photoAuthor }}</div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="filter-card text-center py-5">Опубликованных фотографий по выбранному маршруту пока нет.</div></div>
            @endforelse
        </div>

        @if($photos->hasPages())<div class="mt-5">{{ $photos->links() }}</div>@endif
    </div>
</section>

<div class="pilgrimage-lightbox" id="pilgrimageLightbox" role="dialog" aria-modal="true" aria-labelledby="pilgrimageLightboxTitle" aria-hidden="true" hidden>
    <button class="pilgrimage-lightbox-button pilgrimage-lightbox-close" type="button" data-lightbox-close aria-label="Закрыть"><i class="bi bi-x-lg"></i></button>
    <button class="pilgrimage-lightbox-button pilgrimage-lightbox-prev" type="button" data-lightbox-prev aria-label="Предыдущая фотография"><i class="bi bi-chevron-left"></i></button>
    <button class="pilgrimage-lightbox-button pilgrimage-lightbox-next" type="button" data-lightbox-next aria-label="Следующая фотография"><i class="bi bi-chevron-right"></i></button>

    <div class="pilgrimage-lightbox-dialog">
        <div class="pilgrimage-lightbox-stage" data-lightbox-stage>
            <div class="pilgrimage-lightbox-spinner" data-lightbox-spinner hidden></div>
            <img class="pilgrimage-lightbox-image" data-lightbox-image src="" alt="">
        </div>
        <aside class="pilgrimage-lightbox-info">
            <div class="pilgrimage-lightbox-counter" data-lightbox-counter></div>
            <h2 class="pilgrimage-lightbox-title" id="pilgrimageLightboxTitle" data-lightbox-title></h2>
            <a class="pilgrimage-lightbox-route" data-lightbox-route href="#"><i class="bi bi-signpost-split me-1"></i><span></span></a>
            <p class="pilgrimage-lightbox-description" data-lightbox-description></p>
            <div class="pilgrimage-lightbox-author" data-lightbox-author></div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const lightbox = document.getElementById('pilgrimageLightbox');
    const items = Array.from(document.querySelectorAll('[data-pilgrimage-lightbox]'));
    if (!lightbox || items.length === 0) return;

    const image = lightbox.querySelector('[data-lightbox-image]');
    const title = lightbox.querySelector('[data-lightbox-title]');
    const description = lightbox.querySelector('[data-lightbox-description]');
    const routeLink = lightbox.querySelector('[data-lightbox-route]');
    const routeLabel = routeLink.querySelector('span');
    const author = lightbox.querySelector('[data-lightbox-author]');
    const counter = lightbox.querySelector('[data-lightbox-counter]');
    const spinner = lightbox.querySelector('[data-lightbox-spinner]');
    const closeButton = lightbox.querySelector('[data-lightbox-close]');
    const prevButton = lightbox.querySelector('[data-lightbox-prev]');
    const nextButton = lightbox.querySelector('[data-lightbox-next]');
    const stage = lightbox.querySelector('[data-lightbox-stage]');

    let currentIndex = 0;
    let previouslyFocused = null;
    let touchStartX = null;
    let closeTimer = null;

    function normalizedIndex(index) {
        return (index + items.length) % items.length;
    }

    function preload(index) {
        const item = items[normalizedIndex(index)];
        if (!item) return;
        const preloadImage = new Image();
        preloadImage.src = item.href;
    }

    function showItem(index) {
        currentIndex = normalizedIndex(index);
        const item = items[currentIndex];
        const itemTitle = item.dataset.title || 'Паломническая фотография';
        const itemDescription = item.dataset.description || '';
        const itemRouteTitle = item.dataset.routeTitle || '';
        const itemRouteUrl = item.dataset.routeUrl || '';
        const itemAuthor = item.dataset.author || 'Паломник';

        image.classList.add('is-loading');
        spinner.hidden = false;
        image.src = item.href;
        image.alt = itemTitle;
        title.textContent = itemTitle;
        description.textContent = itemDescription;
        description.hidden = itemDescription === '';
        counter.textContent = `${currentIndex + 1} из ${items.length}`;
        author.textContent = `Фото: ${itemAuthor}`;

        if (itemRouteTitle && itemRouteUrl) {
            routeLabel.textContent = itemRouteTitle;
            routeLink.href = itemRouteUrl;
            routeLink.hidden = false;
        } else {
            routeLink.hidden = true;
            routeLink.removeAttribute('href');
        }

        const showNavigation = items.length > 1;
        prevButton.hidden = !showNavigation;
        nextButton.hidden = !showNavigation;

        preload(currentIndex - 1);
        preload(currentIndex + 1);
    }

    function openLightbox(index, trigger) {
        if (closeTimer) window.clearTimeout(closeTimer);
        previouslyFocused = trigger || document.activeElement;
        showItem(index);
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pilgrimage-lightbox-open');
        window.requestAnimationFrame(() => {
            lightbox.classList.add('is-open');
            closeButton.focus({preventScroll: true});
        });
    }

    function closeLightbox() {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pilgrimage-lightbox-open');
        closeTimer = window.setTimeout(() => {
            lightbox.hidden = true;
            image.src = '';
            previouslyFocused?.focus({preventScroll: true});
        }, 180);
    }

    function previous() {
        showItem(currentIndex - 1);
    }

    function next() {
        showItem(currentIndex + 1);
    }

    items.forEach((item, index) => {
        item.addEventListener('click', event => {
            event.preventDefault();
            openLightbox(index, item);
        });
    });

    image.addEventListener('load', () => {
        image.classList.remove('is-loading');
        spinner.hidden = true;
    });
    image.addEventListener('error', () => {
        image.classList.remove('is-loading');
        spinner.hidden = true;
    });

    closeButton.addEventListener('click', closeLightbox);
    prevButton.addEventListener('click', previous);
    nextButton.addEventListener('click', next);

    lightbox.addEventListener('click', event => {
        if (event.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', event => {
        if (lightbox.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previous();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            next();
        } else if (event.key === 'Home') {
            event.preventDefault();
            showItem(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            showItem(items.length - 1);
        } else if (event.key === 'Tab') {
            const focusable = Array.from(lightbox.querySelectorAll('button:not([hidden]), a[href]:not([hidden])'));
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    stage.addEventListener('touchstart', event => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, {passive: true});

    stage.addEventListener('touchend', event => {
        if (touchStartX === null || items.length < 2) return;
        const endX = event.changedTouches[0]?.clientX ?? touchStartX;
        const distance = endX - touchStartX;
        touchStartX = null;
        if (Math.abs(distance) < 55) return;
        distance > 0 ? previous() : next();
    }, {passive: true});
})();
</script>
@endpush
