@extends('site.layouts.app')

@section('title', $event->title.' — Календарь паломника')
@section('meta_description', \Illuminate\Support\Str::limit($event->short_description ?: $event->description, 155))

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('calendar.index') }}">Календарь</a></li><li class="breadcrumb-item active">{{ $event->title }}</li></ol></nav>
        <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge rounded-pill object-type-badge">{{ $types[$event->type]??$event->type }}</span>@if($event->all_day)<span class="badge rounded-pill text-bg-light">Весь день</span>@endif</div>
        <div class="row align-items-end g-4">
            <div class="col-lg-8"><h1 class="section-title mb-3">{{ $event->title }}</h1><p class="section-lead mb-0">{{ $event->short_description }}</p></div>
            <div class="col-lg-4 text-lg-end"><a class="btn btn-pm-gold" href="{{ route('calendar.ics',$event) }}"><i class="bi bi-calendar-plus me-2"></i>Добавить в календарь</a></div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                @if($event->description)<div class="section-kicker mb-2">Описание</div><h2 class="h2 mb-4">О событии</h2><div class="text-secondary lh-lg mb-5">{!! nl2br(e($event->description)) !!}</div>@endif

                @if($event->pilgrimageObject)
                    <div class="filter-card mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="small text-secondary mb-1">Место проведения</div><h2 class="h4 mb-1">{{ $event->pilgrimageObject->name }}</h2><div class="small text-secondary">{{ $event->pilgrimageObject->address }}</div></div><a class="btn btn-outline-pm" href="{{ route('objects.show',$event->pilgrimageObject) }}">Карточка объекта</a></div></div>
                @endif

                @if($event->pilgrimageRoute)
                    <div class="filter-card mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="small text-secondary mb-1">Связанный маршрут</div><h2 class="h4 mb-0">{{ $event->pilgrimageRoute->title }}</h2></div><a class="btn btn-outline-pm" href="{{ route('routes.show',$event->pilgrimageRoute) }}">Открыть маршрут</a></div></div>
                @endif
            </div>

            <aside class="col-lg-4">
                <div class="position-sticky d-grid gap-3" style="top:105px">
                    <div class="info-card">
                        <h2 class="h5 mb-4">Когда и где</h2>
                        <div class="d-grid gap-3 small">
                            <div><div class="text-secondary mb-1">Начало</div><strong>{{ $event->starts_at->format('d.m.Y') }}{{ $event->all_day?'':', '.$event->starts_at->format('H:i') }}</strong></div>
                            @if($event->ends_at)<div><div class="text-secondary mb-1">Окончание</div><strong>{{ $event->ends_at->format('d.m.Y') }}{{ $event->all_day?'':', '.$event->ends_at->format('H:i') }}</strong></div>@endif
                            <div><div class="text-secondary mb-1">Место</div><strong>{{ $event->location ?: optional($event->pilgrimageObject)->name ?: 'Уточняется' }}</strong>@if($event->address)<div class="text-secondary mt-1">{{ $event->address }}</div>@endif</div>
                            @if($event->capacity)<div><div class="text-secondary mb-1">Количество мест</div><strong>{{ $event->capacity }}</strong></div>@endif
                        </div>
                    </div>

                    @if($event->contact_phone || $event->contact_email)<div class="info-card"><h2 class="h5 mb-3">Контакты</h2>@if($event->contact_phone)<a class="d-block text-decoration-none mb-2" href="tel:{{ $event->contact_phone }}"><i class="bi bi-telephone me-2"></i>{{ $event->contact_phone }}</a>@endif @if($event->contact_email)<a class="d-block text-decoration-none" href="mailto:{{ $event->contact_email }}"><i class="bi bi-envelope me-2"></i>{{ $event->contact_email }}</a>@endif</div>@endif

                    @if($event->registration_url)<a class="btn btn-pm-green py-3" href="{{ $event->registration_url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Записаться</a>@endif
                    <a class="btn btn-outline-pm py-3" href="{{ route('calendar.ics',$event) }}"><i class="bi bi-download me-2"></i>Скачать .ics</a>
                    <a class="btn btn-light py-3" href="{{ route('calendar.index',['month'=>$event->starts_at->format('Y-m')]) }}"><i class="bi bi-arrow-left me-2"></i>Вернуться к календарю</a>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
