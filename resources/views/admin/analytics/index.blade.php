@extends('admin.layouts.app')

@section('title', 'Аналитика поведения')

@push('styles')
<style>
    .analytics-bar { height:10px;border-radius:999px;background:rgba(38,68,59,.12);overflow:hidden; }
    .analytics-bar > span { display:block;height:100%;border-radius:inherit;background:#b08a3e; }
    .daily-chart { display:flex;align-items:end;gap:7px;height:190px;padding-top:20px;overflow-x:auto; }
    .daily-column { min-width:26px;flex:1;max-width:54px;text-align:center; }
    .daily-column-bar { min-height:3px;border-radius:6px 6px 2px 2px;background:#26443b; }
    .funnel-step { padding:18px;border-radius:16px;background:#fffdf9;border:1px solid rgba(111,77,55,.14); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Аналитика поведения</h1>
        <div class="page-subtitle">Поиски, просмотры, маршруты, избранное и воронка бронирования.</div>
    </div>
</div>

<div class="card-soft p-3 mb-4">
    <form class="row g-3 align-items-end" method="GET" action="{{ route('admin.analytics.index') }}">
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="date_from">С даты</label>
            <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="date_to">По дату</label>
            <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}">
        </div>
        <div class="col-md-4 col-lg-2 d-grid"><button class="btn btn-gold" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button></div>
        <div class="col-lg-4"><div class="small text-secondary py-2">IP-адреса не сохраняются открыто: в статистике используется односторонний хеш.</div></div>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['events','bi-activity','Всего действий'],
        ['sessions','bi-people','Сессий'],
        ['searches','bi-search','Поисков'],
        ['no_results','bi-search-heart','Без результатов'],
        ['object_views','bi-building','Просмотров храмов'],
        ['route_views','bi-signpost-split','Просмотров маршрутов'],
        ['day_routes','bi-stars','Маршрутов дня'],
        ['favorites','bi-heart','Добавлений в избранное'],
    ] as $card)
        <div class="col-6 col-lg-3 col-xxl-2">
            <div class="card-soft stat-card">
                <span class="stat-icon"><i class="bi {{ $card[1] }}"></i></span>
                <div class="stat-number">{{ number_format((int)$summary[$card[0]], 0, ',', ' ') }}</div>
                <div class="stat-label">{{ $card[2] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card-soft p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h2 class="h5 mb-1">Активность по дням</h2><div class="small text-secondary">Все зафиксированные действия пользователей</div></div>
            </div>
            @php($maxDaily = max(1, (int)$daily->max('count')))
            <div class="daily-chart">
                @forelse($daily as $day)
                    <div class="daily-column" title="{{ $day['date'] }}: {{ $day['count'] }}">
                        <div class="small fw-semibold mb-1">{{ $day['count'] }}</div>
                        <div class="daily-column-bar" style="height:{{ max(3, round(($day['count'] / $maxDaily) * 135)) }}px"></div>
                        <div class="small text-secondary mt-2">{{ $day['date'] }}</div>
                    </div>
                @empty
                    <div class="text-secondary m-auto">За выбранный период событий пока нет.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-1">Воронка бронирования</h2>
            <div class="small text-secondary mb-4">От открытия формы до созданной заявки</div>
            @php($started = max(1, (int)$summary['booking_started']))
            <div class="d-grid gap-3">
                <div class="funnel-step"><div class="d-flex justify-content-between"><strong>Начали заполнять</strong><span>{{ $summary['booking_started'] }}</span></div><div class="analytics-bar mt-2"><span style="width:100%"></span></div></div>
                <div class="funnel-step"><div class="d-flex justify-content-between"><strong>Создали заявку</strong><span>{{ $summary['booking_created'] }}</span></div><div class="analytics-bar mt-2"><span style="width:{{ min(100, round(($summary['booking_created'] / $started) * 100)) }}%"></span></div><div class="small text-secondary mt-2">Конверсия: {{ $summary['booking_started'] ? number_format(($summary['booking_created'] / $summary['booking_started']) * 100, 1, ',', ' ') : 0 }}%</div></div>
                <div class="funnel-step"><div class="d-flex justify-content-between"><strong>Отменили</strong><span>{{ $summary['booking_cancelled'] }}</span></div><div class="analytics-bar mt-2"><span style="width:{{ min(100, round(($summary['booking_cancelled'] / max(1,$summary['booking_created'])) * 100)) }}%"></span></div></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-1">Поисковые запросы без результатов</h2>
            <div class="small text-secondary mb-3">Главный список для расширения синонимов и наполнения каталога</div>
            <div class="table-responsive">
                <table class="table mb-0"><thead><tr><th>Запрос</th><th class="text-end">Количество</th></tr></thead><tbody>
                @forelse($noResultSearches as $row)
                    <tr><td><strong>{{ $row->search_query }}</strong></td><td class="text-end">{{ $row->aggregate }}</td></tr>
                @empty<tr><td colspan="2" class="text-secondary text-center py-4">Пустых результатов не зафиксировано.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-1">Популярные поисковые запросы</h2>
            <div class="small text-secondary mb-3">Что пользователи ищут чаще всего</div>
            <div class="table-responsive">
                <table class="table mb-0"><thead><tr><th>Запрос</th><th class="text-end">Количество</th></tr></thead><tbody>
                @forelse($topSearches as $row)
                    <tr><td>{{ $row->search_query }}</td><td class="text-end">{{ $row->aggregate }}</td></tr>
                @empty<tr><td colspan="2" class="text-secondary text-center py-4">Поисков пока нет.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-3">Популярные храмы</h2>
            <div class="d-grid gap-3">
                @forelse($topObjects as $row)
                    <div><div class="d-flex justify-content-between gap-3"><span class="text-truncate">{{ $row['name'] }}</span><strong>{{ $row['count'] }}</strong></div><div class="analytics-bar mt-2"><span style="width:{{ min(100, round(($row['count'] / max(1,(int)$topObjects->max('count'))) * 100)) }}%"></span></div></div>
                @empty<div class="text-secondary">Просмотров пока нет.</div>@endforelse
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-3">Популярные маршруты</h2>
            <div class="d-grid gap-3">
                @forelse($topRoutes as $row)
                    <div><div class="d-flex justify-content-between gap-3"><span class="text-truncate">{{ $row['name'] }}</span><strong>{{ $row['count'] }}</strong></div><div class="analytics-bar mt-2"><span style="width:{{ min(100, round(($row['count'] / max(1,(int)$topRoutes->max('count'))) * 100)) }}%"></span></div></div>
                @empty<div class="text-secondary">Просмотров пока нет.</div>@endforelse
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft p-4 h-100">
            <h2 class="h5 mb-3">Используемые фильтры</h2>
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Набор фильтров</th><th class="text-end">Раз</th></tr></thead><tbody>
            @forelse($usedFilters as $row)<tr><td class="small">{{ $row->search_query }}</td><td class="text-end">{{ $row->aggregate }}</td></tr>@empty<tr><td colspan="2" class="text-secondary text-center py-4">Фильтры пока не использовались.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </div>
</div>

<div class="card-soft p-0 overflow-hidden">
    <div class="p-4 border-bottom"><h2 class="h5 mb-1">Последние действия</h2><div class="small text-secondary">Технический журнал событий без открытого хранения IP-адресов</div></div>
    <div class="table-responsive">
        <table class="table mb-0"><thead><tr><th>Время</th><th>Событие</th><th>Пользователь</th><th>Запрос / сущность</th><th>Страница</th></tr></thead><tbody>
        @forelse($recent as $event)
            <tr>
                <td class="small text-secondary text-nowrap">{{ $event->created_at->format('d.m.Y H:i:s') }}</td>
                <td>{{ $eventLabels[$event->event] ?? $event->event }}</td>
                <td>{{ $event->user?->name ?: 'Гость' }}</td>
                <td class="small">@if($event->search_query)<strong>{{ $event->search_query }}</strong>@elseif($event->entity_id){{ $event->entity_type }} #{{ $event->entity_id }}@else—@endif</td>
                <td class="small text-secondary text-break">/{{ $event->path }}</td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-secondary py-5">Событий за выбранный период нет.</td></tr>@endforelse
        </tbody></table>
    </div>
    @if($recent->hasPages())<div class="p-3 border-top">{{ $recent->links() }}</div>@endif
</div>
@endsection
