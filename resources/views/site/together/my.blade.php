@extends('site.layouts.app')

@section('title', 'Мои совместные паломничества — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('together.index') }}">Паломничество вместе</a></li><li class="breadcrumb-item active">Мои группы</li></ol></nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8"><div class="section-kicker mb-2">Личный раздел</div><h1 class="section-title mb-3">Мои совместные паломничества</h1><p class="section-lead mb-0">Здесь находятся созданные вами предложения и группы, к которым вы присоединились.</p></div>
            <div class="col-lg-4 text-lg-end"><a class="btn btn-pm-gold" href="{{ route('together.create') }}"><i class="bi bi-plus-lg me-2"></i>Предложить поездку</a></div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <section class="mb-5">
            <div class="section-kicker mb-2">Я организатор</div>
            <h2 class="h2 mb-4">Созданные предложения</h2>
            <div class="row g-4">
                @forelse($organized as $item)
                    <div class="col-md-6 col-xl-4">
                        <article class="card-pm p-4">
                            <div class="d-flex justify-content-between gap-2 mb-3"><span class="badge rounded-pill {{ $item->status === 'published' ? 'text-bg-success' : ($item->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ['pending' => 'На модерации', 'published' => 'Опубликовано', 'rejected' => 'Отклонено', 'cancelled' => 'Отменено', 'completed' => 'Завершено'][$item->status] ?? $item->status }}</span><span class="small text-secondary"><i class="bi bi-people me-1"></i>{{ $item->approvedParticipantsCount() }}</span></div>
                            <div class="small text-secondary mb-2">{{ $item->starts_at->format('d.m.Y H:i') }}</div>
                            <h3 class="object-title mb-3"><a class="text-decoration-none" href="{{ route('together.show', $item) }}">{{ $item->title }}</a></h3>
                            <div class="small text-secondary mb-4"><i class="bi bi-geo-alt me-1"></i>{{ $item->meeting_place }}</div>
                            <div class="d-flex gap-2"><a class="btn btn-outline-pm flex-grow-1" href="{{ route('together.show', $item) }}">Открыть</a><a class="btn btn-light" href="{{ route('together.edit', $item) }}"><i class="bi bi-pencil"></i></a></div>
                        </article>
                    </div>
                @empty
                    <div class="col-12"><div class="filter-card text-center py-5"><p class="text-secondary mb-3">Вы пока не создавали совместных паломничеств.</p><a class="btn btn-pm-gold" href="{{ route('together.create') }}">Создать первое</a></div></div>
                @endforelse
            </div>
        </section>

        <section>
            <div class="section-kicker mb-2">Я участник</div>
            <h2 class="h2 mb-4">Мои заявки и группы</h2>
            <div class="d-grid gap-3">
                @forelse($memberships as $membership)
                    @php($item = $membership->jointPilgrimage)
                    @if($item)
                        <article class="info-card">
                            <div class="row align-items-center g-3">
                                <div class="col-md">
                                    <div class="d-flex flex-wrap gap-2 mb-2"><span class="badge rounded-pill {{ $membership->status === 'approved' ? 'text-bg-success' : ($membership->status === 'pending' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ['pending' => 'Заявка на рассмотрении', 'approved' => 'Участие подтверждено', 'rejected' => 'Заявка отклонена', 'left' => 'Вы вышли'][$membership->status] ?? $membership->status }}</span></div>
                                    <h3 class="h5 mb-2">{{ $item->title }}</h3>
                                    <div class="small text-secondary">{{ $item->starts_at->format('d.m.Y H:i') }} · {{ $item->meeting_place }}</div>
                                </div>
                                <div class="col-md-auto"><a class="btn btn-outline-pm" href="{{ route('together.show', $item) }}">Открыть группу</a></div>
                            </div>
                        </article>
                    @endif
                @empty
                    <div class="filter-card text-center py-5"><p class="text-secondary mb-3">Вы пока не присоединились ни к одной группе.</p><a class="btn btn-outline-pm" href="{{ route('together.index') }}">Найти паломничество</a></div>
                @endforelse
            </div>
        </section>
    </div>
</section>
@endsection
