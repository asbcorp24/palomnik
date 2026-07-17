@extends('site.profile.layout')

@section('title', 'Заблокированные пользователи — Московский паломник')
@section('profile_title', 'Заблокированные пользователи')
@section('profile_subtitle', 'Управляйте пользователями, чьи предложения и сообщения вы не хотите видеть.')

@section('profile_content')
<div class="profile-card">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><div class="section-kicker mb-1">Безопасность</div><h2 class="h4 mb-0">Чёрный список</h2></div><span class="badge rounded-pill text-bg-light">{{ $blocks->total() }}</span></div>
    <div class="d-grid gap-3">
        @forelse($blocks as $block)
            <div class="info-card"><div class="row align-items-center g-3"><div class="col-md"><div class="fw-semibold">{{ optional($block->blocked)->name ?: 'Пользователь удалён' }}</div><div class="small text-secondary">{{ optional($block->blocked)->email }} · заблокирован {{ $block->created_at->format('d.m.Y') }}</div></div>@if($block->blocked)<div class="col-md-auto"><form method="POST" action="{{ route('safety.blocks.destroy', $block->blocked) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-pm" type="submit"><i class="bi bi-person-check me-1"></i>Разблокировать</button></form></div>@endif</div></div>
        @empty
            <div class="empty-state"><i class="bi bi-shield-check display-5 d-block mb-3"></i>Вы никого не блокировали.</div>
        @endforelse
    </div>
    @if($blocks->hasPages())<div class="mt-4">{{ $blocks->links() }}</div>@endif
</div>
@endsection
