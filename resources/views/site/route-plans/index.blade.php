@extends('site.profile.layout')

@section('title', 'Мои маршруты — Московский паломник')
@section('profile_title', 'Мои маршруты')
@section('profile_subtitle', 'Составляйте индивидуальные пути из храмов и святынь.')

@section('profile_content')
<div class="d-flex justify-content-end mb-4"><a class="btn btn-pm-gold" href="{{ route('route-plans.create') }}"><i class="bi bi-plus-lg me-2"></i>Создать маршрут</a></div>

<div class="row g-4">
    @forelse($plans as $plan)
        <div class="col-md-6">
            <article class="profile-card h-100">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <span class="category-icon"><i class="bi bi-signpost-split"></i></span>
                    <span class="badge rounded-pill object-type-badge">{{ ['walk' => 'Пешком', 'public' => 'Транспорт', 'car' => 'Автомобиль'][$plan->transport_mode] ?? $plan->transport_mode }}</span>
                </div>
                <h2 class="h5 mb-3">{{ $plan->name }}</h2>
                <div class="small text-secondary mb-4">{{ $plan->objects_count }} точек · около {{ $plan->estimated_minutes ?: '—' }} мин.</div>
                <div class="d-flex gap-2 mt-auto">
                    <a class="btn btn-pm-green flex-grow-1" href="{{ route('route-plans.show', $plan) }}">Открыть</a>
                    <a class="btn btn-light" href="{{ route('route-plans.edit', $plan) }}"><i class="bi bi-pencil"></i></a>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><div class="profile-card empty-state"><i class="bi bi-signpost-split display-4 d-block mb-3"></i><h2 class="h4">Маршрутов пока нет</h2><p>Выберите минимум два объекта и сохраните собственный путь.</p><a class="btn btn-pm-gold" href="{{ route('route-plans.create') }}">Создать первый маршрут</a></div></div>
    @endforelse
</div>

@if($plans->hasPages())<div class="mt-4">{{ $plans->links() }}</div>@endif
@endsection
