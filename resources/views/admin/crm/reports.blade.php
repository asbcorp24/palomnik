@extends('admin.layouts.app')

@section('title', 'Отчёты CRM')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-2"><a class="btn btn-sm btn-light" href="{{ route('admin.crm.index') }}"><i class="bi bi-arrow-left"></i></a><span class="text-secondary small">CRM</span></div>
        <h1 class="page-title">Отчёты по паломническим заявкам</h1>
        <div class="page-subtitle">Заявки, участники, решения, явка, оплаты и эффективность поездок.</div>
    </div>
    <a class="btn btn-light" href="{{ route('admin.crm.export', ['from' => $filters['from'], 'to' => $filters['to'], 'trip_id' => $filters['trip_id']]) }}"><i class="bi bi-filetype-csv me-1"></i>Выгрузить заявки</a>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.crm.reports') }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label" for="from">Период с</label><input class="form-control" id="from" name="from" type="date" value="{{ $filters['from'] }}"></div>
        <div class="col-md-3"><label class="form-label" for="to">по</label><input class="form-control" id="to" name="to" type="date" value="{{ $filters['to'] }}"></div>
        <div class="col-md-4"><label class="form-label" for="trip_id">Поездка</label><select class="form-select" id="trip_id" name="trip_id"><option value="">Все поездки</option>@foreach($trips as $trip)<option value="{{ $trip->id }}" @selected((string)$filters['trip_id'] === (string)$trip->id)>{{ $trip->starts_at->format('d.m.Y') }} — {{ optional($trip->pilgrimageRoute)->title ?: $trip->title }}</option>@endforeach</select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Построить</button></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number">{{ number_format($summary['bookings'], 0, ',', ' ') }}</div><div class="stat-label">Заявок</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number">{{ number_format($summary['people'], 0, ',', ' ') }}</div><div class="stat-label">Участников</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number text-success">{{ number_format($summary['going'], 0, ',', ' ') }}</div><div class="stat-label">Подтвердили поездку</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number text-danger">{{ number_format($summary['not_going'], 0, ',', ' ') }}</div><div class="stat-label">Отказались</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number text-primary">{{ number_format($summary['attended'], 0, ',', ' ') }}</div><div class="stat-label">Фактически прибыли</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number text-danger">{{ number_format($summary['no_show'], 0, ',', ' ') }}</div><div class="stat-label">Не явились</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number">{{ number_format($summary['paid'], 2, ',', ' ') }} ₽</div><div class="stat-label">Оплачено</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-soft stat-card"><div class="stat-number text-danger">{{ number_format($summary['debt'], 2, ',', ' ') }} ₽</div><div class="stat-label">Ожидается к оплате</div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-soft p-0 overflow-hidden h-100">
            <div class="p-4 border-bottom"><h2 class="h4 mb-1">Результаты по поездкам</h2><div class="small text-secondary">Количество заявок, участников и оплат.</div></div>
            @if($byTrip->isEmpty())
                <div class="p-5 text-center text-secondary">Данных за период нет.</div>
            @else
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Поездка</th><th>Заявок</th><th>Участников</th><th>Подтверждено</th><th>Сумма</th><th>Оплачено</th><th></th></tr></thead><tbody>@foreach($byTrip as $row)<tr><td><strong>{{ optional(optional($row['trip'])->pilgrimageRoute)->title ?: optional($row['trip'])->title ?: '—' }}</strong><div class="small text-secondary">{{ optional(optional($row['trip'])->starts_at)->format('d.m.Y H:i') }}</div></td><td>{{ $row['bookings'] }}</td><td>{{ $row['people'] }}</td><td>{{ $row['confirmed'] }}</td><td>{{ number_format($row['amount'], 2, ',', ' ') }} ₽</td><td>{{ number_format($row['paid'], 2, ',', ' ') }} ₽</td><td class="text-end">@if($row['trip'])<a class="btn btn-sm btn-light" href="{{ route('admin.crm.trip', $row['trip']) }}"><i class="bi bi-arrow-right"></i></a>@endif</td></tr>@endforeach</tbody></table></div>
            @endif
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-soft p-4 mb-4">
            <h2 class="h5 mb-3">Источники заявок</h2>
            <div class="d-grid gap-3">
                @forelse($bySource as $source => $count)
                    @php($maxSource = max(1, (int)$bySource->max()))
                    <div><div class="d-flex justify-content-between small mb-1"><span>{{ $sources[$source] ?? $source }}</span><strong>{{ $count }}</strong></div><div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ round($count / $maxSource * 100) }}%;background:var(--pilgrim-gold)"></div></div></div>
                @empty<div class="text-secondary small">Нет данных.</div>@endforelse
            </div>
        </div>
        <div class="card-soft p-4">
            <h2 class="h5 mb-3">Статусы заявок</h2>
            <div class="d-grid gap-2">@forelse($byStatus as $status => $count)<div class="d-flex justify-content-between"><span>{{ $bookingStatuses[$status] ?? $status }}</span><strong>{{ $count }}</strong></div>@empty<div class="text-secondary small">Нет данных.</div>@endforelse</div>
        </div>
    </div>
</div>

<div class="card-soft p-0 overflow-hidden">
    <div class="p-4 border-bottom"><h2 class="h4 mb-1">Динамика заявок</h2><div class="small text-secondary">По дням выбранного периода.</div></div>
    @if($daily->isEmpty())<div class="p-5 text-center text-secondary">Данных за период нет.</div>@else<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Дата</th><th>Заявок</th><th>Участников</th><th>Сумма</th></tr></thead><tbody>@foreach($daily as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['bookings'] }}</td><td>{{ $row['people'] }}</td><td>{{ number_format($row['amount'], 2, ',', ' ') }} ₽</td></tr>@endforeach</tbody></table></div>@endif
</div>
@endsection
