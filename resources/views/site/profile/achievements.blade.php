@extends('site.profile.layout')

@section('title', 'Достижения — Московский паломник')
@section('profile_title', 'Достижения и квесты')
@section('profile_subtitle', 'Посещайте святые места, проходите тематические пути и получайте награды.')

@section('profile_content')
<div class="row g-4">
    @forelse($achievements as $achievement)
        @php($isEarned = $earned->has($achievement->id))
        <div class="col-md-6 col-xl-4">
            <article class="profile-card h-100 {{ $isEarned ? '' : 'opacity-75' }}">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <span class="category-icon" style="width:64px;height:64px;font-size:1.55rem"><i class="bi {{ $achievement->icon ?: 'bi-award' }}"></i></span>
                    @if($isEarned)
                        <span class="status-badge status-published"><i class="bi bi-check-circle"></i>Получено</span>
                    @else
                        <span class="status-badge status-draft">В процессе</span>
                    @endif
                </div>
                <div class="small text-secondary text-uppercase mb-2">{{ $achievement->badge_level }}</div>
                <h2 class="h5 mb-3">{{ $achievement->title }}</h2>
                <p class="small text-secondary mb-4">{{ $achievement->description }}</p>
                <div class="d-flex justify-content-between align-items-center mt-auto">
                    <strong style="color:var(--pm-gold-dark)">{{ $achievement->points }} баллов</strong>
                    @if($achievement->condition_value)<span class="small text-secondary">Цель: {{ $achievement->condition_value }}</span>@endif
                </div>
                @if($isEarned && $earned[$achievement->id]->pivot->awarded_at)
                    <div class="small text-secondary mt-3">Получено {{ \Illuminate\Support\Carbon::parse($earned[$achievement->id]->pivot->awarded_at)->format('d.m.Y') }}</div>
                @endif
            </article>
        </div>
    @empty
        <div class="col-12"><div class="profile-card empty-state">Достижения ещё не настроены администратором.</div></div>
    @endforelse
</div>
@endsection
