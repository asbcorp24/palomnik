@extends('site.layouts.app')

@section('title', 'Паломничество вместе — Московский паломник')
@section('meta_description', 'Найдите попутчиков, создайте совместное паломничество и договоритесь о поездке внутри платформы.')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item active">Паломничество вместе</li></ol></nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Найдите единомышленников</div>
                <h1 class="section-title mb-3">Паломничество вместе</h1>
                <p class="section-lead mb-0">Создайте предложение о совместной поездке, соберите группу, согласуйте место встречи и обсудите детали в закрытом чате участников.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @auth
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a class="btn btn-outline-pm" href="{{ route('together.my') }}"><i class="bi bi-people me-2"></i>Мои группы</a>
                        <a class="btn btn-pm-gold" href="{{ route('together.create') }}"><i class="bi bi-plus-lg me-2"></i>Предложить поездку</a>
                    </div>
                @else
                    <a class="btn btn-pm-gold" href="{{ route('register') }}"><i class="bi bi-person-plus me-2"></i>Зарегистрироваться</a>
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-4 mb-5">
            <div class="col-md-4"><div class="feature-step"><div class="step-number mb-4">1</div><h2 class="h5">Создайте предложение</h2><p class="text-secondary mb-0">Укажите дату, место встречи, транспорт, маршрут и число участников.</p></div></div>
            <div class="col-md-4"><div class="feature-step"><div class="step-number mb-4">2</div><h2 class="h5">Соберите группу</h2><p class="text-secondary mb-0">Принимайте заявки вручную или разрешите свободное присоединение.</p></div></div>
            <div class="col-md-4"><div class="feature-step"><div class="step-number mb-4">3</div><h2 class="h5">Договоритесь внутри</h2><p class="text-secondary mb-0">После подтверждения участники получают доступ к обсуждению и контактам организатора.</p></div></div>
        </div>

        <form class="filter-card mb-5" method="GET" action="{{ route('together.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label" for="q">Поиск</label>
                    <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, маршрут или место встречи">
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="transport">Транспорт</label>
                    <select class="form-select" id="transport" name="transport">
                        <option value="">Любой</option>
                        @foreach($transportModes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['transport'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="date">Дата</label>
                    <input class="form-control" id="date" name="date" type="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-md-4 col-lg-1 d-grid">
                    <button class="btn btn-pm-green" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <div class="text-secondary">Найдено предложений: <strong class="text-dark">{{ $items->total() }}</strong></div>
        </div>

        <div class="row g-4">
            @forelse($items as $item)
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <span class="badge rounded-pill object-type-badge">{{ $transportModes[$item->transport_mode] ?? $item->transport_mode }}</span>
                            <span class="small text-secondary"><i class="bi bi-people me-1"></i>{{ $item->approvedParticipantsCount() }}@if($item->max_participants) / {{ $item->max_participants }}@endif</span>
                        </div>
                        <div class="small text-secondary mb-2"><i class="bi bi-calendar3 me-2"></i>{{ $item->starts_at->format('d.m.Y H:i') }}</div>
                        <h2 class="object-title mb-3"><a class="text-decoration-none" href="{{ route('together.show', $item) }}">{{ $item->title }}</a></h2>
                        <p class="text-secondary small mb-3">{{ \Illuminate\Support\Str::limit($item->description, 160) }}</p>
                        <div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-2"></i>{{ $item->meeting_place }}</div>
                        <div class="small text-secondary mb-4"><i class="bi bi-person-circle me-2"></i>Организатор: {{ optional($item->organizer)->name }}</div>
                        @if($item->pilgrimageRoute)
                            <div class="small mb-4"><i class="bi bi-signpost-split me-2"></i>{{ $item->pilgrimageRoute->title }}</div>
                        @endif
                        <a class="btn btn-outline-pm w-100" href="{{ route('together.show', $item) }}">Открыть и присоединиться</a>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="filter-card text-center py-5">
                        <div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-people"></i></div>
                        <h2 class="h4 mb-3">Пока нет подходящих предложений</h2>
                        <p class="text-secondary mb-4">Станьте первым организатором совместного паломничества.</p>
                        @auth<a class="btn btn-pm-gold" href="{{ route('together.create') }}">Создать предложение</a>@else<a class="btn btn-pm-gold" href="{{ route('register') }}">Зарегистрироваться</a>@endauth
                    </div>
                </div>
            @endforelse
        </div>

        @if($items->hasPages())<div class="mt-5">{{ $items->links() }}</div>@endif
    </div>
</section>
@endsection
