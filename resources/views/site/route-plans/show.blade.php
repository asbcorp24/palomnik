@extends('site.profile.layout')

@section('title', $plan->name.' — Московский паломник')
@section('profile_title', $plan->name)
@section('profile_subtitle', ($transportModes[$plan->transport_mode] ?? $plan->transport_mode).' · '.$plan->objects->count().' точек · около '.($plan->estimated_minutes ?: '—').' мин.')

@section('profile_content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="profile-card">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div><div class="section-kicker mb-1">Порядок пути</div><h2 class="h4 mb-0">Точки маршрута</h2></div>
                <a class="btn btn-sm btn-outline-pm" href="{{ route('route-plans.edit', $plan) }}"><i class="bi bi-pencil me-1"></i>Изменить</a>
            </div>
            <div class="d-grid gap-3">
                @foreach($plan->objects as $index => $object)
                    <article class="route-plan-step">
                        <span class="step-number flex-shrink-0">{{ $index + 1 }}</span>
                        @if($object->coverMedia && $object->coverMedia->url)<img src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}" style="width:88px;height:72px;object-fit:cover;border-radius:12px">@endif
                        <div class="flex-grow-1">
                            <div class="small text-secondary">{{ optional($object->objectType)->name }}</div>
                            <h3 class="h6 mb-1"><a class="text-decoration-none" href="{{ route('objects.show', $object) }}">{{ $object->name }}</a></h3>
                            <div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $object->address }}</div>
                            <div class="small mt-2"><i class="bi bi-clock me-1"></i>Планируемая остановка: {{ $object->pivot->stay_minutes }} мин.</div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="profile-card position-sticky" style="top:105px">
            <h2 class="h5 mb-4">Запустить маршрут</h2>
            <a class="btn btn-pm-gold w-100 py-3 mb-3" href="{{ $yandexRouteUrl }}" target="_blank" rel="noopener"><i class="bi bi-map me-2"></i>Открыть в Яндекс Картах</a>
            <div class="small text-secondary mb-4">Построение пути и точное время выполняются сервисом Яндекс Карт с учётом выбранного способа передвижения.</div>
            @if($plan->notes)<div class="info-card mb-3"><div class="small text-secondary mb-1">Заметки</div><div>{!! nl2br(e($plan->notes)) !!}</div></div>@endif
            <div class="d-flex gap-2">
                <a class="btn btn-outline-pm flex-grow-1" href="{{ route('route-plans.edit', $plan) }}">Редактировать</a>
                <form method="POST" action="{{ route('route-plans.destroy', $plan) }}" onsubmit="return confirm('Удалить маршрут?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>
    </div>
</div>
@endsection
