@extends('site.layouts.app')

@section('title', 'Паломнические маршруты — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item active">Маршруты</li></ol></nav>
        <div class="section-kicker mb-2">Следующий модуль</div>
        <h1 class="section-title mb-3">Паломнические маршруты</h1>
        <p class="section-lead mb-0">Раздел уже включён в структуру сайта. Здесь появятся однодневные, тематические, семейные и молодёжные маршруты.</p>
    </div>
</section>

<section class="section-space">
    <div class="container">
        <div class="row g-4">
            @foreach([
                ['Однодневный', 'Святыни исторического центра', 'Пешеходный маршрут по храмам центральной части Москвы.', 'bi-sun'],
                ['Семейный', 'Храмы и история Москвы', 'Спокойный маршрут с понятной программой для родителей и детей.', 'bi-people'],
                ['Тематический', 'Места новомучеников', 'Маршрут по храмам и памятным местам, связанным с новомучениками.', 'bi-signpost-split'],
            ] as $route)
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm">
                        <div class="object-placeholder"><i class="bi {{ $route[3] }}"></i></div>
                        <div class="p-4">
                            <span class="badge rounded-pill object-type-badge mb-3">{{ $route[0] }}</span>
                            <h2 class="object-title mb-3">{{ $route[1] }}</h2>
                            <p class="text-secondary small mb-4">{{ $route[2] }}</p>
                            <button class="btn btn-light w-100" type="button" disabled>Готовится к публикации</button>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="filter-card mt-5 p-4 p-lg-5 text-center">
            <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:105px;aspect-ratio:1"><i class="bi bi-tools"></i></div>
            <h2 class="h3 mb-3">Что будет разработано дальше</h2>
            <p class="text-secondary mx-auto mb-4" style="max-width:760px">Каталог маршрутов, программа по точкам, даты поездок, стоимость, количество мест, запись участников, бронирование и электронные билеты.</p>
            <a class="btn btn-pm-green" href="{{ route('objects.index') }}">Пока посмотреть объекты</a>
        </div>
    </div>
</section>
@endsection
