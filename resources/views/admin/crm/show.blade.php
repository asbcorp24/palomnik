@extends('admin.layouts.app')

@section('title', 'Заявка '.$booking->ticket_code)

@push('styles')
<style>
    .crm-panel-title { font-family: Inter, sans-serif; font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; color: var(--pilgrim-muted); }
    .crm-activity { position: relative; padding-left: 28px; }
    .crm-activity::before { content:''; position:absolute; left:8px; top:7px; bottom:-18px; width:1px; background:var(--pilgrim-border); }
    .crm-activity:last-child::before { display:none; }
    .crm-activity-dot { position:absolute; left:1px; top:7px; width:15px; height:15px; border:3px solid #fff; border-radius:50%; background:var(--pilgrim-gold); box-shadow:0 0 0 1px var(--pilgrim-border); }
    .participant-card { border-left:4px solid var(--pilgrim-green); }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <a class="btn btn-sm btn-light" href="{{ route('admin.crm.index') }}"><i class="bi bi-arrow-left"></i></a>
            <span class="badge rounded-pill text-bg-light">{{ $booking->ticket_code ?: '#'.$booking->id }}</span>
            <span class="badge rounded-pill {{ in_array($booking->status, ['confirmed','completed']) ? 'badge-published' : 'badge-draft' }}">{{ $bookingStatuses[$booking->status] ?? $booking->status }}</span>
            <span class="badge rounded-pill text-bg-light">{{ $crmStages[$booking->crm_stage] ?? $booking->crm_stage }}</span>
        </div>
        <h1 class="page-title">{{ $booking->contact_name }}</h1>
        <div class="page-subtitle">
            {{ optional(optional($booking->trip)->pilgrimageRoute)->title ?: optional($booking->trip)->title ?: 'Поездка не указана' }}
            · {{ optional(optional($booking->trip)->starts_at)->format('d.m.Y H:i') ?: 'дата не указана' }}
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($booking->trip)<a class="btn btn-light" href="{{ route('admin.crm.trip', $booking->trip) }}"><i class="bi bi-clipboard-check me-1"></i>Ведомость поездки</a>@endif
        @if($booking->phone)<a class="btn btn-outline-green" href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}"><i class="bi bi-telephone me-1"></i>Позвонить</a>@endif
        @if($booking->email)<a class="btn btn-outline-green" href="mailto:{{ $booking->email }}"><i class="bi bi-envelope me-1"></i>Написать</a>@endif
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <form class="card-soft p-4 mb-4" method="POST" action="{{ route('admin.crm.update', $booking) }}">
            @csrf
            @method('PUT')
            <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div><div class="crm-panel-title mb-1">Карточка заявки</div><h2 class="h4 mb-0">Контакт и обработка</h2></div>
                <div class="small text-secondary">Создана {{ $booking->created_at->format('d.m.Y H:i') }}</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required" for="contact_name">Контактное лицо</label><input class="form-control" id="contact_name" name="contact_name" value="{{ old('contact_name', $booking->contact_name) }}" required></div>
                <div class="col-md-3"><label class="form-label" for="phone">Телефон</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $booking->phone) }}"></div>
                <div class="col-md-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email', $booking->email) }}"></div>

                <div class="col-md-4"><label class="form-label required" for="status">Решение по заявке</label><select class="form-select" id="status" name="status" required>@foreach($bookingStatuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $booking->status) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label required" for="crm_stage">Этап CRM</label><select class="form-select" id="crm_stage" name="crm_stage" required>@foreach($crmStages as $value => $label)<option value="{{ $value }}" @selected(old('crm_stage', $booking->crm_stage) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label required" for="priority">Приоритет</label><select class="form-select" id="priority" name="priority" required>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', $booking->priority) === $value)>{{ $label }}</option>@endforeach</select></div>

                <div class="col-md-4"><label class="form-label required" for="payment_status">Статус оплаты</label><select class="form-select" id="payment_status" name="payment_status" required>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', $booking->payment_status) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label required" for="total_amount">Сумма, ₽</label><input class="form-control" id="total_amount" name="total_amount" type="number" min="0" step="0.01" value="{{ old('total_amount', $booking->total_amount) }}" required></div>
                <div class="col-md-4"><label class="form-label required" for="source">Источник</label><select class="form-select" id="source" name="source" required>@foreach($sources as $value => $label)<option value="{{ $value }}" @selected(old('source', $booking->source) === $value)>{{ $label }}</option>@endforeach</select></div>

                <div class="col-md-4"><label class="form-label" for="payment_provider">Способ оплаты</label><input class="form-control" id="payment_provider" name="payment_provider" value="{{ old('payment_provider', $booking->payment_provider) }}" placeholder="Касса, перевод, эквайринг"></div>
                <div class="col-md-4"><label class="form-label" for="payment_reference">Номер платежа</label><input class="form-control" id="payment_reference" name="payment_reference" value="{{ old('payment_reference', $booking->payment_reference) }}"></div>
                <div class="col-md-4"><label class="form-label" for="assigned_to">Ответственный</label><select class="form-select" id="assigned_to" name="assigned_to"><option value="">Не назначен</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string)old('assigned_to', $booking->assigned_to) === (string)$manager->id)>{{ $manager->name }}</option>@endforeach</select></div>

                <div class="col-md-6"><label class="form-label" for="next_contact_at">Следующий контакт</label><input class="form-control" id="next_contact_at" name="next_contact_at" type="datetime-local" value="{{ old('next_contact_at', optional($booking->next_contact_at)->format('Y-m-d\TH:i')) }}"></div>
                <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-3"><input class="form-check-input" id="mark_contacted" name="mark_contacted" type="checkbox" value="1"><label class="form-check-label" for="mark_contacted">Отметить, что сейчас связались с клиентом</label></div></div>

                <div class="col-12"><label class="form-label" for="internal_notes">Внутренние заметки</label><textarea class="form-control" id="internal_notes" name="internal_notes" rows="4">{{ old('internal_notes', $booking->internal_notes) }}</textarea></div>
                <div class="col-12"><label class="form-label" for="cancellation_reason">Причина отказа или отмены</label><textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="2">{{ old('cancellation_reason', $booking->cancellation_reason) }}</textarea></div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                <div class="small text-secondary">Контактов: {{ $booking->contact_attempts }}@if($booking->last_contact_at) · последний {{ $booking->last_contact_at->format('d.m.Y H:i') }}@endif</div>
                <button class="btn btn-gold" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить заявку</button>
            </div>
        </form>

        <section class="card-soft p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div><div class="crm-panel-title mb-1">Кто едет</div><h2 class="h4 mb-0">Участники: {{ $booking->participants->count() }}</h2></div>
                <div class="small text-secondary">Прибыли: {{ $booking->participants->where('attendance_status', 'attended')->count() }}</div>
            </div>

            <div class="d-grid gap-3 mb-4">
                @foreach($booking->participants as $participant)
                    <article class="info-card participant-card">
                        <form method="POST" action="{{ route('admin.crm.participants.update', $participant) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4"><label class="form-label">ФИО @if($participant->is_primary)<span class="badge text-bg-light">основной</span>@endif</label><input class="form-control" name="full_name" value="{{ $participant->full_name }}" required></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label">Телефон</label><input class="form-control" name="phone" value="{{ $participant->phone }}"></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label">Дата рождения</label><input class="form-control" name="birth_date" type="date" value="{{ optional($participant->birth_date)->format('Y-m-d') }}"></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label">Решение</label><select class="form-select" name="decision_status">@foreach($decisionStatuses as $value => $label)<option value="{{ $value }}" @selected($participant->decision_status === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label">Явка</label><select class="form-select" name="attendance_status">@foreach($attendanceStatuses as $value => $label)<option value="{{ $value }}" @selected($participant->attendance_status === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-lg-3 col-md-4"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="{{ $participant->email }}"></div>
                                <div class="col-lg-2 col-md-4"><label class="form-label">Оплачено, ₽</label><input class="form-control" name="paid_amount" type="number" min="0" step="0.01" value="{{ $participant->paid_amount }}"></div>
                                <div class="col-lg-5"><label class="form-label">Примечание</label><input class="form-control" name="notes" value="{{ $participant->notes }}"></div>
                                <div class="col-lg-2 d-grid"><button class="btn btn-outline-green" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить</button></div>
                            </div>
                        </form>
                        @if(!$participant->is_primary)
                            <form class="mt-2 text-end" method="POST" action="{{ route('admin.crm.participants.destroy', $participant) }}" onsubmit="return confirm('Удалить участника из заявки?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Удалить участника</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            <details class="info-card">
                <summary class="fw-semibold" style="cursor:pointer">Добавить участника</summary>
                <form class="row g-3 align-items-end mt-1" method="POST" action="{{ route('admin.crm.participants.store', $booking) }}">
                    @csrf
                    <div class="col-lg-4"><label class="form-label required">ФИО</label><input class="form-control" name="full_name" required></div>
                    <div class="col-lg-2"><label class="form-label">Телефон</label><input class="form-control" name="phone"></div>
                    <div class="col-lg-2"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
                    <div class="col-lg-2"><label class="form-label">Дата рождения</label><input class="form-control" name="birth_date" type="date"></div>
                    <div class="col-lg-2"><label class="form-label">Решение</label><select class="form-select" name="decision_status">@foreach($decisionStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">Явка</label><select class="form-select" name="attendance_status">@foreach($attendanceStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="form-label">Оплачено, ₽</label><input class="form-control" name="paid_amount" type="number" min="0" step="0.01" value="0"></div>
                    <div class="col-lg-6"><label class="form-label">Примечание</label><input class="form-control" name="notes"></div>
                    <div class="col-lg-2 d-grid"><button class="btn btn-gold" type="submit"><i class="bi bi-person-plus me-1"></i>Добавить</button></div>
                </form>
            </details>
        </section>
    </div>

    <aside class="col-xl-4">
        <div class="card-soft p-4 mb-4">
            <div class="crm-panel-title mb-2">Сводка</div>
            <div class="d-grid gap-3">
                <div class="d-flex justify-content-between"><span class="text-secondary">Участников</span><strong>{{ $booking->participants_count }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Едут</span><strong class="text-success">{{ $booking->participants->where('decision_status', 'going')->count() }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Не едут</span><strong class="text-danger">{{ $booking->participants->where('decision_status', 'not_going')->count() }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Прибыли</span><strong>{{ $booking->participants->where('attendance_status', 'attended')->count() }}</strong></div>
                <hr class="my-0">
                <div class="d-flex justify-content-between"><span class="text-secondary">Сумма</span><strong>{{ number_format((float)$booking->total_amount, 2, ',', ' ') }} ₽</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Оплата</span><strong>{{ $paymentStatuses[$booking->payment_status] ?? $booking->payment_status }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Ответственный</span><strong>{{ optional($booking->assignedTo)->name ?: 'Не назначен' }}</strong></div>
            </div>
            @if($booking->notes)<hr><div class="small text-secondary mb-1">Комментарий клиента</div><div class="small lh-lg">{!! nl2br(e($booking->notes)) !!}</div>@endif
        </div>

        <div class="card-soft p-4 mb-4">
            <div class="crm-panel-title mb-2">Добавить контакт</div>
            <form method="POST" action="{{ route('admin.crm.notes.store', $booking) }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="type">Тип</label><select class="form-select" id="type" name="type"><option value="note">Заметка</option><option value="call">Телефонный звонок</option><option value="email">Email</option><option value="message">Сообщение</option></select></div>
                <div class="mb-3"><label class="form-label required" for="body">Результат контакта</label><textarea class="form-control" id="body" name="body" rows="4" required placeholder="Что обсудили, какое решение, когда связаться снова"></textarea></div>
                <button class="btn btn-gold w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Добавить в историю</button>
            </form>
        </div>

        <div class="card-soft p-4">
            <div class="crm-panel-title mb-2">История</div>
            <div class="d-grid gap-3">
                @forelse($booking->activities as $activity)
                    <div class="crm-activity">
                        <span class="crm-activity-dot"></span>
                        <div class="small fw-semibold">{{ optional($activity->user)->name ?: 'Система' }}</div>
                        <div class="small text-secondary mb-1">{{ optional($activity->created_at)->format('d.m.Y H:i') }}</div>
                        <div class="small lh-lg">{!! nl2br(e($activity->body)) !!}</div>
                    </div>
                @empty
                    <div class="text-secondary small">История пока пуста.</div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
@endsection
