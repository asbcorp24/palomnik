@extends('site.layouts.app')

@section('title', 'Календарь событий — Московский паломник')
@section('meta_description', 'Богослужения, престольные праздники, крестные ходы, встречи и паломнические поездки Москвы и Московской области.')

@push('styles')
<style>
    .calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); border:1px solid var(--pm-border); border-radius:22px; overflow:hidden; background:#fff; }
    .calendar-weekday { padding:.8rem; text-align:center; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--pm-muted); background:var(--pm-cream); border-bottom:1px solid var(--pm-border); }
    .calendar-day { min-height:145px; padding:.7rem; border-right:1px solid var(--pm-border); border-bottom:1px solid var(--pm-border); background:#fff; }
    .calendar-day:nth-child(7n) { border-right:0; }
    .calendar-day.outside { background:#faf8f4; color:#aaa; }
    .calendar-day.today { box-shadow:inset 0 0 0 2px var(--pm-gold); }
    .calendar-date { width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:700; }
    .calendar-day.today .calendar-date { color:#fff; background:var(--pm-gold); }
    .calendar-event-chip { display:block; margin-top:.45rem; padding:.42rem .5rem; border-radius:9px; color:var(--pm-green); background:rgba(38,68,59,.09); font-size:.72rem; line-height:1.25; text-decoration:none; }
    .calendar-event-chip:hover { color:#fff; background:var(--pm-green); }
    @media(max-width:991.98px){ .calendar-grid { display:none; } }
</style>
@endpush

@section('content')
@php
    $monthNames = [1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
@endphp
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item active">Календарь</li></ol></nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">События и богослужения</div>
                <h1 class="section-title mb-3">Календарь паломника</h1>
                <p class="section-lead mb-0">Богослужения, праздники, крестные ходы, лекции, семейные встречи и организованные поездки.</p>
            </div>
            <div class="col-lg-4 text-lg-end"><a class="btn btn-outline-pm" href="{{ route('calendar.index', ['month' => now()->format('Y-m')]) }}"><i class="bi bi-calendar-check me-2"></i>Текущий месяц</a></div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <form class="filter-card mb-4" method="GET" action="{{ route('calendar.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label class="form-label" for="q">Поиск</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название, храм или место"></div>
                <div class="col-md-4 col-lg-3"><label class="form-label" for="type">Тип события</label><select class="form-select" id="type" name="type"><option value="">Все типы</option>@foreach($types as $value=>$label)<option value="{{ $value }}" @selected(($filters['type']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-3"><label class="form-label" for="object">Храм или объект</label><select class="form-select" id="object" name="object"><option value="">Все объекты</option>@foreach($objects as $object)<option value="{{ $object->id }}" @selected((string)($filters['object']??'')===(string)$object->id)>{{ $object->name }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-2 d-grid"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><button class="btn btn-pm-green" type="submit"><i class="bi bi-funnel me-2"></i>Показать</button></div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <a class="btn btn-light" href="{{ route('calendar.index', array_merge(request()->except('month'), ['month'=>$previousMonth])) }}"><i class="bi bi-chevron-left"></i></a>
            <h2 class="h3 mb-0 text-center">{{ $monthNames[$month->month] }} {{ $month->year }}</h2>
            <a class="btn btn-light" href="{{ route('calendar.index', array_merge(request()->except('month'), ['month'=>$nextMonth])) }}"><i class="bi bi-chevron-right"></i></a>
        </div>

        <div class="calendar-grid mb-5">
            @foreach(['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $weekday)<div class="calendar-weekday">{{ $weekday }}</div>@endforeach
            @foreach($days as $day)
                <div class="calendar-day {{ $day['in_month']?'':'outside' }} {{ $day['is_today']?'today':'' }}">
                    <div class="calendar-date">{{ $day['date']->day }}</div>
                    @foreach($day['events']->take(3) as $event)
                        <a class="calendar-event-chip" href="{{ route('calendar.show',$event) }}" title="{{ $event->title }}">
                            @unless($event->all_day)<strong>{{ $event->starts_at->format('H:i') }}</strong> @endunless{{ \Illuminate\Support\Str::limit($event->title,42) }}
                        </a>
                    @endforeach
                    @if($day['events']->count()>3)<div class="small text-secondary mt-2">Ещё {{ $day['events']->count()-3 }}</div>@endif
                </div>
            @endforeach
        </div>

        <div class="d-lg-none mb-4"><div class="alert alert-light border"><i class="bi bi-phone me-2"></i>На небольшом экране события показаны удобным списком по датам.</div></div>

        <div class="d-flex justify-content-between align-items-end gap-3 mb-4"><div><div class="section-kicker mb-2">Список событий</div><h2 class="h2 mb-0">{{ $monthNames[$month->month] }} {{ $month->year }}</h2></div><div class="text-secondary">{{ $events->count() }} событий</div></div>
        <div class="d-grid gap-3">
            @forelse($events as $event)
                <article class="info-card">
                    <div class="row align-items-center g-3">
                        <div class="col-auto text-center" style="min-width:78px"><div class="small text-uppercase text-secondary">{{ $monthNames[$event->starts_at->month] }}</div><div class="pm-serif display-6 lh-1">{{ $event->starts_at->format('d') }}</div><div class="small text-secondary">{{ $event->all_day?'весь день':$event->starts_at->format('H:i') }}</div></div>
                        <div class="col-md"><div class="d-flex flex-wrap gap-2 mb-2"><span class="badge rounded-pill object-type-badge">{{ $types[$event->type]??$event->type }}</span>@if($event->pilgrimageObject)<span class="badge rounded-pill text-bg-light">{{ $event->pilgrimageObject->name }}</span>@endif</div><h3 class="h5 mb-2"><a class="text-decoration-none" href="{{ route('calendar.show',$event) }}">{{ $event->title }}</a></h3><p class="small text-secondary mb-1">{{ $event->short_description }}</p><div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $event->location ?: $event->address ?: optional($event->pilgrimageObject)->address ?: 'Место уточняется' }}</div></div>
                        <div class="col-md-auto"><a class="btn btn-outline-pm" href="{{ route('calendar.show',$event) }}">Подробнее</a></div>
                    </div>
                </article>
            @empty
                <div class="filter-card text-center py-5"><i class="bi bi-calendar-x display-4 text-secondary"></i><h3 class="h4 mt-3">Событий не найдено</h3><p class="text-secondary mb-0">Измените фильтры или перейдите к другому месяцу.</p></div>
            @endforelse
        </div>
    </div>
</section>
@endsection
