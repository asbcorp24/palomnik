@extends('admin.layouts.app')

@section('title', 'Ведомость поездки')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-2"><a class="btn btn-sm btn-light" href="{{ route('admin.crm.index', ['trip_id' => $trip->id]) }}"><i class="bi bi-arrow-left"></i></a><span class="text-secondary small">CRM</span></div>
        <h1 class="page-title">Ведомость поездки</h1>
        <div class="page-subtitle">{{ optional($trip->pilgrimageRoute)->title ?: $trip->title }} · {{ $trip->starts_at->format('d.m.Y H:i') }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="{{ route('admin.crm.trip.export', $trip) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        <a class="btn btn-light" href="{{ route('admin.crm.trip.print', $trip) }}" target="_blank"><i class="bi bi-printer me-1"></i>Печать</a>
        <a class="btn btn-gold" href="{{ route('admin.crm.create') }}"><i class="bi bi-plus-lg me-1"></i>Добавить заявку</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number">{{ $summary['capacity'] ?? '∞' }}</div><div class="stat-label">Вместимость</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number">{{ $summary['booked'] }}</div><div class="stat-label">Занято мест</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number text-success">{{ $summary['going'] }}</div><div class="stat-label">Едут</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number text-warning">{{ $summary['pending'] }}</div><div class="stat-label">Не определились</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number text-primary">{{ $summary['attended'] }}</div><div class="stat-label">Прибыли</div></div></div>
    <div class="col-sm-6 col-xl-2"><div class="card-soft stat-card"><div class="stat-number text-danger">{{ $summary['no_show'] }}</div><div class="stat-label">Не явились</div></div></div>
</div>

<div class="card-soft p-4 mb-4">
    <div class="row g-3 small">
        <div class="col-md-4"><strong>Место встречи:</strong><br>{{ $trip->meeting_point ?: 'Не указано' }}</div>
        <div class="col-md-2"><strong>Статус:</strong><br>{{ $trip->status }}</div>
        <div class="col-md-2"><strong>Заявок:</strong><br>{{ $trip->bookings->count() }}</div>
        <div class="col-md-2"><strong>Сумма:</strong><br>{{ number_format($summary['amount'], 2, ',', ' ') }} ₽</div>
        <div class="col-md-2"><strong>Оплачено:</strong><br>{{ number_format($summary['paid_amount'], 2, ',', ' ') }} ₽</div>
    </div>
</div>

<div class="card-soft p-0 overflow-hidden">
    @if($participants->isEmpty())
        <div class="p-5 text-center text-secondary"><i class="bi bi-people display-5 d-block mb-3"></i>Участники ещё не добавлены.</div>
    @else
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>№</th><th>Участник</th><th>Контакты</th><th>Заявка</th><th>Решение</th><th>Явка</th><th>Оплачено</th><th>Примечание</th><th class="text-end">Сохранить</th></tr></thead>
                <tbody>
                @foreach($participants as $participant)
                    @php($booking = $participant->booking)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $participant->full_name }}</strong>@if($participant->is_primary)<div class="small text-secondary">Основной контакт</div>@endif</td>
                        <td class="small">{{ $participant->phone ?: $booking->phone ?: '—' }}<br>{{ $participant->email ?: $booking->email ?: '—' }}</td>
                        <td><a href="{{ route('admin.crm.show', $booking) }}">{{ $booking->ticket_code ?: '#'.$booking->id }}</a><div class="small text-secondary">{{ $bookingStatuses[$booking->status] ?? $booking->status }} · {{ $paymentStatuses[$booking->payment_status] ?? $booking->payment_status }}</div></td>
                        <td colspan="5" class="p-0">
                            <form class="row g-2 align-items-center p-2 m-0" method="POST" action="{{ route('admin.crm.participants.update', $participant) }}">
                                @csrf
                                @method('PUT')
                                <input name="full_name" type="hidden" value="{{ $participant->full_name }}">
                                <input name="phone" type="hidden" value="{{ $participant->phone }}">
                                <input name="email" type="hidden" value="{{ $participant->email }}">
                                <input name="birth_date" type="hidden" value="{{ optional($participant->birth_date)->format('Y-m-d') }}">
                                <div class="col-md-3"><select class="form-select form-select-sm" name="decision_status">@foreach($decisionStatuses as $value => $label)<option value="{{ $value }}" @selected($participant->decision_status === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-3"><select class="form-select form-select-sm" name="attendance_status">@foreach($attendanceStatuses as $value => $label)<option value="{{ $value }}" @selected($participant->attendance_status === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-md-2"><input class="form-control form-control-sm" name="paid_amount" type="number" min="0" step="0.01" value="{{ $participant->paid_amount }}"></div>
                                <div class="col-md-3"><input class="form-control form-control-sm" name="notes" value="{{ $participant->notes }}" placeholder="Примечание"></div>
                                <div class="col-md-1 d-grid"><button class="btn btn-sm btn-outline-green" type="submit"><i class="bi bi-check-lg"></i></button></div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
