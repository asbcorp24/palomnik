@extends('site.layouts.app')

@section('title', 'Кабинет представителя — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="section-kicker mb-2">Проверенный контент</div>
                <h1 class="section-title mb-3">Кабинет представителя</h1>
                <p class="section-lead mb-0">Обновляйте расписание, контакты, описание и материалы закреплённых храмов, а также проверяйте электронные билеты участников.</p>
            </div>
            <div class="col-lg-4 text-lg-end"><div class="d-flex flex-wrap justify-content-lg-end gap-2"><a class="btn btn-outline-pm" href="{{ route('service.tickets.scanner') }}"><i class="bi bi-qr-code-scan me-2"></i>Проверка билетов</a><a class="btn btn-pm-gold" href="{{ route('service.objects.index') }}"><i class="bi bi-buildings me-2"></i>Мои объекты</a></div></div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-3 mb-5">
            @foreach([
                ['Объекты в управлении', $stats['objects'], 'bi-buildings'],
                ['Ожидают подтверждения', $stats['pending_assignments'], 'bi-person-check'],
                ['Изменения на проверке', $stats['pending_updates'], 'bi-pencil-square'],
                ['Материалы на проверке', $stats['pending_media'], 'bi-images'],
            ] as $card)
                <div class="col-6 col-xl-3"><div class="stat-box rounded-4 h-100"><div class="stat-number">{{ $card[1] }}</div><div class="stat-label"><i class="bi {{ $card[2] }} me-1"></i>{{ $card[0] }}</div></div></div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-end mb-4"><div><div class="section-kicker mb-2">Закрепления</div><h2 class="h2 mb-0">Доступные объекты</h2></div><a class="btn btn-outline-pm" href="{{ route('service.objects.index') }}">Все объекты</a></div>
                <div class="d-grid gap-3">
                    @forelse($assignments as $assignment)
                        <article class="info-card">
                            <div class="row align-items-center g-3">
                                <div class="col-md">
                                    <div class="d-flex flex-wrap gap-2 mb-2"><span class="badge rounded-pill {{ $assignment->status === 'approved' ? 'text-bg-success' : ($assignment->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ['approved' => 'Подтверждено', 'pending' => 'На проверке', 'rejected' => 'Отклонено'][$assignment->status] ?? $assignment->status }}</span><span class="badge rounded-pill text-bg-light">{{ optional(optional($assignment->pilgrimageObject)->objectType)->name }}</span></div>
                                    <h3 class="h5 mb-2">{{ optional($assignment->pilgrimageObject)->name ?: 'Объект удалён' }}</h3>
                                    <div class="small text-secondary">{{ optional($assignment->pilgrimageObject)->address }}</div>
                                </div>
                                <div class="col-md-auto">@if($assignment->status === 'approved' && $assignment->pilgrimageObject)<a class="btn btn-outline-pm" href="{{ route('service.objects.edit', $assignment->pilgrimageObject) }}">Редактировать</a>@endif</div>
                            </div>
                        </article>
                    @empty
                        <div class="filter-card text-center py-5"><i class="bi bi-building-lock display-5 text-secondary"></i><p class="text-secondary mt-3 mb-0">Администратор ещё не закрепил за вами паломнические объекты.</p></div>
                    @endforelse
                </div>
            </div>

            <div class="col-lg-5">
                <div class="section-kicker mb-2">История</div><h2 class="h2 mb-4">Последние заявки</h2>
                <div class="d-grid gap-3">
                    @forelse($recentRequests as $updateRequest)
                        <div class="info-card"><div class="d-flex justify-content-between gap-3 mb-2"><strong>{{ optional($updateRequest->pilgrimageObject)->name }}</strong><span class="badge rounded-pill {{ $updateRequest->status === 'approved' ? 'text-bg-success' : ($updateRequest->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $updateRequest->status }}</span></div><div class="small text-secondary">{{ $updateRequest->created_at->format('d.m.Y H:i') }}</div>@if($updateRequest->review_note)<div class="small mt-2">{{ $updateRequest->review_note }}</div>@endif</div>
                    @empty<div class="filter-card text-secondary">Заявок на изменение пока нет.</div>@endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
