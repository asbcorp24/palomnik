@extends('admin.layouts.app')

@section('title', 'CRM паломнических заявок')

@push('styles')
<style>
    .crm-stat { min-height: 132px; }
    .crm-stat .value { font-size: 1.7rem; font-weight: 700; }
    .crm-priority-urgent { border-left: 4px solid #c0392b; }
    .crm-priority-high { border-left: 4px solid #d68910; }
    .crm-priority-normal { border-left: 4px solid #2d6a5d; }
    .crm-priority-low { border-left: 4px solid #98a39f; }
    .crm-overdue { background: rgba(192,57,43,.07); }
    .crm-contact a { text-decoration: none; }
    .crm-toolbar { position: sticky; bottom: 0; z-index: 5; box-shadow: 0 -10px 30px rgba(37,33,29,.08); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title"><i class="bi bi-headset me-2"></i>CRM паломнических заявок</h1>
        <div class="page-subtitle">Заявки, контакты, состав групп, оплаты, явка и отчётность.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="{{ route('admin.crm.reports') }}"><i class="bi bi-bar-chart me-1"></i>Отчёты</a>
        <a class="btn btn-light" href="{{ route('admin.crm.export', request()->query()) }}"><i class="bi bi-filetype-csv me-1"></i>Экспорт CSV</a>
        <a class="btn btn-gold" href="{{ route('admin.crm.create') }}"><i class="bi bi-plus-lg me-1"></i>Новая заявка</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-inbox"></i></div><div class="value mt-3">{{ number_format($summary['bookings'], 0, ',', ' ') }}</div><div class="stat-label">Заявок по фильтру</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-people"></i></div><div class="value mt-3">{{ number_format($summary['people'], 0, ',', ' ') }}</div><div class="stat-label">Всего заявленных участников</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-person-check"></i></div><div class="value mt-3">{{ number_format($summary['confirmed_people'], 0, ',', ' ') }}</div><div class="stat-label">Подтверждено участников</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-telephone-outbound"></i></div><div class="value mt-3">{{ number_format($summary['overdue'], 0, ',', ' ') }}</div><div class="stat-label">Просроченных контактов</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="value mt-3">{{ number_format($summary['pending'], 0, ',', ' ') }}</div><div class="stat-label">Ждут решения</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div><div class="value mt-3">{{ number_format($summary['paid'], 0, ',', ' ') }} ₽</div><div class="stat-label">Оплачено</div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft stat-card crm-stat"><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div><div class="value mt-3">{{ number_format($summary['debt'], 0, ',', ' ') }} ₽</div><div class="stat-label">Ожидается к оплате</div></div>
    </div>
</div>

<form class="card-soft p-3 mb-4" method="GET" action="{{ route('admin.crm.index') }}">
    <div class="row g-3 align-items-end">
        <div class="col-xl-4 col-md-6">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ФИО, телефон, email, билет или маршрут">
        </div>
        <div class="col-xl-4 col-md-6">
            <label class="form-label" for="trip_id">Поездка</label>
            <select class="form-select" id="trip_id" name="trip_id">
                <option value="">Все поездки</option>
                @foreach($trips as $trip)
                    <option value="{{ $trip->id }}" @selected((string)($filters['trip_id'] ?? '') === (string)$trip->id)>
                        {{ $trip->starts_at->format('d.m.Y H:i') }} — {{ optional($trip->pilgrimageRoute)->title ?: $trip->title }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label" for="status">Решение</label>
            <select class="form-select" id="status" name="status"><option value="">Все</option>@foreach($bookingStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label" for="payment_status">Оплата</label>
            <select class="form-select" id="payment_status" name="payment_status"><option value="">Все</option>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label" for="crm_stage">Этап CRM</label>
            <select class="form-select" id="crm_stage" name="crm_stage"><option value="">Все</option>@foreach($crmStages as $value => $label)<option value="{{ $value }}" @selected(($filters['crm_stage'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label" for="priority">Приоритет</label>
            <select class="form-select" id="priority" name="priority"><option value="">Все</option>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label" for="source">Источник</label>
            <select class="form-select" id="source" name="source"><option value="">Все</option>@foreach($sources as $value => $label)<option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-xl-3 col-md-6">
            <label class="form-label" for="assigned_to">Ответственный</label>
            <select class="form-select" id="assigned_to" name="assigned_to">
                <option value="">Все менеджеры</option>
                @foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string)($filters['assigned_to'] ?? '') === (string)$manager->id)>{{ $manager->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-4"><label class="form-label" for="from">Заявки с</label><input class="form-control" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label" for="to">по</label><input class="form-control" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="col-xl-1 col-md-4"><label class="form-label" for="per_page">Строк</label><select class="form-select" id="per_page" name="per_page">@foreach([25,50,100] as $count)<option value="{{ $count }}" @selected((int)($filters['per_page'] ?? 25) === $count)>{{ $count }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button></div>
        <div class="col-xl-1 col-md-4 d-grid"><a class="btn btn-light" href="{{ route('admin.crm.index') }}" title="Сбросить фильтры"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>

<form method="POST" action="{{ route('admin.crm.bulk') }}">
    @csrf
    <div class="card-soft p-0 overflow-hidden">
        @if($bookings->isEmpty())
            <div class="p-5 text-center text-secondary"><i class="bi bi-inbox display-5 d-block mb-3"></i>Заявок по выбранным условиям нет.</div>
        @else
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th style="width:42px"><input class="form-check-input" id="selectAll" type="checkbox"></th><th>Заявка и контакт</th><th>Поездка</th><th>Состав</th><th>Решение</th><th>Оплата</th><th>CRM</th><th>Ответственный</th><th class="text-end">Действия</th></tr></thead>
                    <tbody>
                    @foreach($bookings as $booking)
                        @php($isOverdue = $booking->next_contact_at && $booking->next_contact_at->isPast() && !in_array($booking->crm_stage, ['ready','closed'], true))
                        <tr class="crm-priority-{{ $booking->priority }} {{ $isOverdue ? 'crm-overdue' : '' }}">
                            <td><input class="form-check-input booking-check" type="checkbox" name="booking_ids[]" value="{{ $booking->id }}"></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                    <a class="fw-semibold" href="{{ route('admin.crm.show', $booking) }}">{{ $booking->contact_name }}</a>
                                    <span class="badge rounded-pill text-bg-light">{{ $booking->ticket_code ?: '#'.$booking->id }}</span>
                                </div>
                                <div class="small text-secondary crm-contact">
                                    @if($booking->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}"><i class="bi bi-telephone me-1"></i>{{ $booking->phone }}</a>@endif
                                    @if($booking->email)<span class="mx-1">·</span><a href="mailto:{{ $booking->email }}">{{ $booking->email }}</a>@endif
                                </div>
                                <div class="small text-secondary mt-1">Создана {{ $booking->created_at->format('d.m.Y H:i') }} · {{ $sources[$booking->source] ?? $booking->source }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ optional(optional($booking->trip)->pilgrimageRoute)->title ?: optional($booking->trip)->title ?: '—' }}</div>
                                <div class="small text-secondary">{{ optional(optional($booking->trip)->starts_at)->format('d.m.Y H:i') ?: 'Дата не указана' }}</div>
                                @if($booking->trip)<a class="small" href="{{ route('admin.crm.trip', $booking->trip) }}">Ведомость поездки</a>@endif
                            </td>
                            <td>
                                <div><strong>{{ $booking->participants_count }}</strong> чел.</div>
                                <div class="small text-success">Едут: {{ $booking->going_count }}</div>
                                @if($booking->not_going_count)<div class="small text-danger">Не едут: {{ $booking->not_going_count }}</div>@endif
                                @if($booking->attended_count)<div class="small text-primary">Прибыли: {{ $booking->attended_count }}</div>@endif
                            </td>
                            <td><span class="badge rounded-pill {{ in_array($booking->status, ['confirmed','completed']) ? 'badge-published' : 'badge-draft' }}">{{ $bookingStatuses[$booking->status] ?? $booking->status }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ number_format((float)$booking->total_amount, 2, ',', ' ') }} ₽</div>
                                <div class="small {{ $booking->payment_status === 'paid' ? 'text-success' : 'text-danger' }}">{{ $paymentStatuses[$booking->payment_status] ?? $booking->payment_status }}</div>
                            </td>
                            <td>
                                <div>{{ $crmStages[$booking->crm_stage] ?? $booking->crm_stage }}</div>
                                @if($booking->next_contact_at)<div class="small {{ $isOverdue ? 'text-danger fw-semibold' : 'text-secondary' }}"><i class="bi bi-clock me-1"></i>{{ $booking->next_contact_at->format('d.m.Y H:i') }}</div>@else<div class="small text-secondary">Контакт не назначен</div>@endif
                            </td>
                            <td>{{ optional($booking->assignedTo)->name ?: 'Не назначен' }}</td>
                            <td class="text-end text-nowrap"><a class="btn btn-sm btn-gold" href="{{ route('admin.crm.show', $booking) }}"><i class="bi bi-arrow-right"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="crm-toolbar border-top p-3 bg-white">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-2 col-md-4"><label class="form-label small">Решение</label><select class="form-select form-select-sm" name="status"><option value="">Не менять</option>@foreach($bookingStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2 col-md-4"><label class="form-label small">Оплата</label><select class="form-select form-select-sm" name="payment_status"><option value="">Не менять</option>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2 col-md-4"><label class="form-label small">Этап CRM</label><select class="form-select form-select-sm" name="crm_stage"><option value="">Не менять</option>@foreach($crmStages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-3 col-md-6"><label class="form-label small">Ответственный</label><select class="form-select form-select-sm" name="assigned_to"><option value="">Не менять</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select></div>
                    <div class="col-lg-3 col-md-6 d-grid"><button class="btn btn-outline-green btn-sm" type="submit"><i class="bi bi-pencil-square me-1"></i>Изменить выбранные</button></div>
                </div>
            </div>
        @endif
    </div>
</form>

@if($bookings->hasPages())<div class="mt-4">{{ $bookings->links() }}</div>@endif
@endsection

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.booking-check').forEach(item => item.checked = this.checked);
});
</script>
@endpush
