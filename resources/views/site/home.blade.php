@extends('site.layouts.app')

@section('title', 'Московский паломник — храмы, святыни и маршруты')
@section('meta_description', 'Интерактивная карта храмов и святынь Москвы и Московской области, карточки объектов и паломнические маршруты.')

@section('content')
<section class="hero">
    <div class="container hero-content">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-eyebrow mb-3">Паломничество по Москве и Подмосковью</div>
                <h1 class="hero-title mb-4">Святые места становятся ближе</h1>
                <p class="hero-lead mb-4">Найдите храм, узнайте о святынях и расписании, сохраните интересные места и подготовьте собственный путь.</p>
                <form class="search-panel d-flex align-items-center gap-2" action="{{ route('objects.index') }}" method="GET">
                    <i class="bi bi-search ms-3 text-secondary"></i>
                    <input class="form-control" name="q" placeholder="Название храма, адрес или святыня" aria-label="Поиск храмов и святынь">
                    <button class="btn btn-pm-gold px-4 py-3 rounded-4" type="submit">Найти</button>
                </form>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-pm-green px-4" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Открыть карту</a>
                    <a class="btn btn-outline-pm px-4" href="{{ route('routes.index') }}"><i class="bi bi-signpost-split me-2"></i>Смотреть маршруты</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="phone-stage">
                    <div class="phone">
                        <div class="phone-notch"></div>
                        <div class="phone-screen">
                            <div class="mini-map">
                                <div class="mini-search"><i class="bi bi-search me-2"></i>Поиск храмов...</div>
                                <span class="map-pin"><i class="bi bi-cross"></i></span>
                                <span class="map-pin"><i class="bi bi-cross"></i></span>
                                <span class="map-pin"><i class="bi bi-cross"></i></span>
                                <span class="map-pin"><i class="bi bi-cross"></i></span>
                            </div>
                            <div class="phone-card">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge rounded-pill object-type-badge">Храм</span>
                                    <span class="small text-secondary"><i class="bi bi-star-fill text-warning me-1"></i>4.9</span>
                                </div>
                                <h3 class="h5 mb-2">Места веры рядом</h3>
                                <p class="small text-secondary mb-3">История, святыни, расписание и удобный путь до объекта.</p>
                                <div class="btn btn-pm-gold w-100">Открыть карточку</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container stat-strip">
    <div class="row g-3 g-md-0">
        <div class="col-6 col-md-4"><div class="stat-box h-100"><div class="stat-number">{{ $stats['objects'] }}</div><div class="stat-label">опубликованных объектов</div></div></div>
        <div class="col-6 col-md-4"><div class="stat-box h-100"><div class="stat-number">{{ $stats['sanctities'] }}</div><div class="stat-label">святынь в реестре</div></div></div>
        <div class="col-12 col-md-4"><div class="stat-box h-100"><div class="stat-number">{{ $stats['routes'] }}</div><div class="stat-label">маршрутов готовится к публикации</div></div></div>
    </div>
</div>

<section class="section-space">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5">
            <div>
                <div class="section-kicker mb-2">Единый каталог</div>
                <h2 class="section-title mb-2">Храмы и святыни</h2>
                <p class="section-lead mb-0">Карточки объектов наполняются через административную панель и сразу появляются на сайте и в будущем мобильном приложении.</p>
            </div>
            <a class="btn btn-outline-pm" href="{{ route('objects.index') }}">Весь каталог <i class="bi bi-arrow-right ms-1"></i></a>
        </div>

        <div class="row g-4">
            @forelse($featuredObjects as $object)
                <div class="col-md-6 col-xl-4">@include('site.partials.object-card', ['object' => $object])</div>
            @empty
                @foreach([
                    ['Храм', 'Храм Христа Спасителя', 'Москва, улица Волхонка', 'bi-buildings'],
                    ['Монастырь', 'Свято-Троицкая Сергиева лавра', 'Сергиев Посад', 'bi-bank'],
                    ['Часовня', 'Иверская часовня', 'Москва, Красная площадь', 'bi-building'],
                ] as $demo)
                    <div class="col-md-6 col-xl-4">
                        <article class="card-pm">
                            <div class="object-placeholder"><i class="bi {{ $demo[3] }}"></i></div>
                            <div class="p-4">
                                <span class="badge rounded-pill object-type-badge mb-3">{{ $demo[0] }}</span>
                                <h3 class="object-title mb-2">{{ $demo[1] }}</h3>
                                <div class="object-meta mb-3"><i class="bi bi-geo-alt me-1"></i>{{ $demo[2] }}</div>
                                <p class="small text-secondary mb-0">Демонстрационная карточка. Добавьте объект в административной панели, и реальные данные появятся здесь.</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            @endforelse
        </div>
    </div>
</section>

<section class="section-space section-soft">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-kicker mb-2">Выберите направление</div>
            <h2 class="section-title mb-3">Что вы ищете сегодня?</h2>
            <p class="section-lead mx-auto mb-0">Фильтры помогают быстро перейти к нужному типу паломнического объекта.</p>
        </div>
        <div class="row g-3">
            @forelse($types as $type)
                <div class="col-md-6 col-xl-3">
                    <a class="category-card h-100" href="{{ route('objects.index', ['type' => $type->slug]) }}">
                        <span class="category-icon"><i class="bi bi-geo-alt"></i></span>
                        <span>
                            <span class="fw-semibold d-block">{{ $type->name }}</span>
                            <span class="small text-secondary">{{ $type->published_objects_count }} объектов</span>
                        </span>
                    </a>
                </div>
            @empty
                @foreach(['Храмы', 'Монастыри', 'Часовни', 'Святые источники'] as $label)
                    <div class="col-md-6 col-xl-3">
                        <div class="category-card h-100">
                            <span class="category-icon"><i class="bi bi-geo-alt"></i></span>
                            <span><span class="fw-semibold d-block">{{ $label }}</span><span class="small text-secondary">Справочник готов к наполнению</span></span>
                        </div>
                    </div>
                @endforeach
            @endforelse
        </div>
    </div>
</section>

<section class="section-space">
    <div class="container">
        <div class="row align-items-end g-4 mb-5">
            <div class="col-lg-7">
                <div class="section-kicker mb-2">Простой путь</div>
                <h2 class="section-title mb-3">От интереса — к паломничеству</h2>
                <p class="section-lead mb-0">Сайт уже показывает единый реестр. По мере разработки сюда добавятся бронирование, электронные билеты, отметки о посещениях и достижения.</p>
            </div>
        </div>
        <div class="row g-4">
            @foreach([
                ['Найдите место', 'Используйте поиск, фильтры или интерактивную карту.'],
                ['Изучите карточку', 'Посмотрите историю, святыни, фотографии, контакты и расписание.'],
                ['Постройте путь', 'Перейдите к маршруту и подготовьте посещение заранее.'],
            ] as $index => $step)
                <div class="col-md-4">
                    <div class="feature-step">
                        <div class="step-number mb-4">{{ $index + 1 }}</div>
                        <h3 class="h5 mb-3">{{ $step[0] }}</h3>
                        <p class="text-secondary mb-0">{{ $step[1] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
