@extends('site.layouts.app')

@section('title', 'Храмы и святыни — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-3">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li>
                <li class="breadcrumb-item active">Храмы и святыни</li>
            </ol>
        </nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Единый реестр</div>
                <h1 class="section-title mb-3">Храмы, монастыри и святыни</h1>
                <p class="section-lead mb-0">Ищите объекты по названию, адресу, типу, викариатству и благочинию.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a class="btn btn-pm-green" href="{{ route('map') }}"><i class="bi bi-map me-2"></i>Показать на карте</a>
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-5" method="GET" action="{{ route('objects.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="q">Поиск</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, адрес или святыня">
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="type">Тип</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Все типы</option>
                        @foreach($types as $type)
                            <option value="{{ $type->slug }}" @selected(($filters['type'] ?? '') === $type->slug)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="vicariate">Викариатство</label>
                    <select class="form-select" id="vicariate" name="vicariate">
                        <option value="">Все</option>
                        @foreach($vicariates as $vicariate)
                            <option value="{{ $vicariate->slug }}" @selected(($filters['vicariate'] ?? '') === $vicariate->slug)>{{ $vicariate->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="deanery">Благочиние</label>
                    <select class="form-select" id="deanery" name="deanery">
                        <option value="">Все</option>
                        @foreach($deaneries as $deanery)
                            <option value="{{ $deanery->slug }}" data-vicariate="{{ optional($deanery->vicariate)->slug }}" @selected(($filters['deanery'] ?? '') === $deanery->slug)>{{ $deanery->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label" for="sort">Сортировка</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>По названию</option>
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Сначала новые</option>
                    </select>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-pm-gold px-4" type="submit"><i class="bi bi-funnel me-2"></i>Применить</button>
                    <a class="btn btn-light px-4" href="{{ route('objects.index') }}">Сбросить</a>
                </div>
            </div>
        </form>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="text-secondary">Найдено объектов: <strong class="text-dark">{{ $objects->total() }}</strong></div>
        </div>

        <div class="row g-4">
            @forelse($objects as $object)
                <div class="col-md-6 col-xl-4">@include('site.partials.object-card', ['object' => $object])</div>
            @empty
                <div class="col-12">
                    <div class="filter-card text-center py-5">
                        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-search"></i></div>
                        <h2 class="h4 mb-3">Объекты не найдены</h2>
                        <p class="text-secondary mb-4">Измените параметры поиска или добавьте новые объекты через административную панель.</p>
                        <a class="btn btn-outline-pm" href="{{ route('objects.index') }}">Очистить фильтры</a>
                    </div>
                </div>
            @endforelse
        </div>

        @if($objects->hasPages())
            <div class="mt-5">{{ $objects->links() }}</div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const vicariate = document.getElementById('vicariate');
    const deanery = document.getElementById('deanery');
    if (!vicariate || !deanery) return;

    function filterDeaneries() {
        const selected = vicariate.value;
        Array.from(deanery.options).forEach(function (option, index) {
            if (index === 0) return;
            const visible = !selected || option.dataset.vicariate === selected;
            option.hidden = !visible;
            if (!visible && option.selected) deanery.value = '';
        });
    }

    vicariate.addEventListener('change', filterDeaneries);
    filterDeaneries();
})();
</script>
@endpush
