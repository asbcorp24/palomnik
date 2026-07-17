@extends('site.layouts.app')

@section('title', 'Уведомления — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8"><div class="section-kicker mb-2">Личный кабинет</div><h1 class="section-title mb-3">Уведомления</h1><p class="section-lead mb-0">Заявки, сообщения групп, модерация контента и изменения статусов.</p></div>
            <div class="col-lg-4 text-lg-end"><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-pm" type="submit"><i class="bi bi-check2-all me-2"></i>Прочитать все</button></form></div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container" style="max-width:980px">
        <div class="d-grid gap-3">
            @forelse($notifications as $notification)
                <article class="info-card {{ $notification->read_at ? '' : 'border-success' }}">
                    <div class="d-flex align-items-start gap-3">
                        <span class="info-icon flex-shrink-0"><i class="bi {{ data_get($notification->data, 'icon', 'bi-bell') }}"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{{ data_get($notification->data, 'title', 'Уведомление') }}</strong><span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span></div>
                            <p class="text-secondary mb-3">{{ data_get($notification->data, 'body') }}</p>
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PUT')<button class="btn btn-sm {{ $notification->read_at ? 'btn-light' : 'btn-outline-pm' }}" type="submit">{{ data_get($notification->data, 'url') ? 'Открыть' : 'Отметить прочитанным' }}</button></form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="filter-card text-center py-5"><div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-bell"></i></div><h2 class="h4 mb-3">Уведомлений пока нет</h2><p class="text-secondary mb-0">Здесь будут появляться важные события платформы.</p></div>
            @endforelse
        </div>
        @if($notifications->hasPages())<div class="mt-5">{{ $notifications->links() }}</div>@endif
    </div>
</section>
@endsection
