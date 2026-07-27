@extends('admin.layouts.app')

@section('title', 'Новая заявка')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-2"><a class="btn btn-sm btn-light" href="{{ route('admin.crm.index') }}"><i class="bi bi-arrow-left"></i></a><span class="text-secondary small">CRM</span></div>
        <h1 class="page-title">Новая паломническая заявка</h1>
        <div class="page-subtitle">Для заявок по телефону, email, VK, личному обращению или от партнёра.</div>
    </div>
</div>

<form class="card-soft p-4" method="POST" action="{{ route('admin.crm.store') }}">
    @csrf
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label required" for="trip_id">Поездка</label>
                    <select class="form-select @error('trip_id') is-invalid @enderror" id="trip_id" name="trip_id" required>
                        <option value="">Выберите поездку</option>
                        @foreach($trips as $trip)
                            <option value="{{ $trip->id }}" @selected((string)old('trip_id') === (string)$trip->id)>
                                {{ $trip->starts_at->format('d.m.Y H:i') }} — {{ optional($trip->pilgrimageRoute)->title ?: $trip->title }}
                                @if($trip->capacity !== null) ({{ $trip->booked_count }}/{{ $trip->capacity }} мест) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('trip_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6"><label class="form-label required" for="contact_name">Контактное лицо</label><input class="form-control @error('contact_name') is-invalid @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required>@error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="phone">Телефон</label><input class="form-control" id="phone" name="phone" value="{{ old('phone') }}"></div>
                <div class="col-md-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}"></div>

                <div class="col-md-3"><label class="form-label required" for="participants_count">Участников</label><input class="form-control" id="participants_count" name="participants_count" type="number" min="1" max="100" value="{{ old('participants_count', 1) }}" required></div>
                <div class="col-md-3"><label class="form-label" for="total_amount">Общая сумма, ₽</label><input class="form-control" id="total_amount" name="total_amount" type="number" min="0" step="0.01" value="{{ old('total_amount') }}" placeholder="Рассчитается автоматически"></div>
                <div class="col-md-3"><label class="form-label required" for="source">Источник</label><select class="form-select" id="source" name="source" required>@foreach($sources as $value => $label)<option value="{{ $value }}" @selected(old('source', 'phone') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label required" for="priority">Приоритет</label><select class="form-select" id="priority" name="priority" required>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>@endforeach</select></div>

                <div class="col-12">
                    <label class="form-label" for="participant_names_text">ФИО участников</label>
                    <textarea class="form-control" id="participant_names_text" name="participant_names_text" rows="5" placeholder="Каждое ФИО с новой строки. Если список не заполнен, система создаст участников автоматически.">{{ old('participant_names_text') }}</textarea>
                </div>

                <div class="col-12"><label class="form-label" for="notes">Комментарий клиента</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea></div>
                <div class="col-12"><label class="form-label" for="internal_notes">Внутренняя заметка</label><textarea class="form-control" id="internal_notes" name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="info-card mb-3">
                <h2 class="h5 mb-3">Обработка</h2>
                <div class="mb-3"><label class="form-label required" for="status">Решение</label><select class="form-select" id="status" name="status" required>@foreach($bookingStatuses as $value => $label)<option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label required" for="crm_stage">Этап CRM</label><select class="form-select" id="crm_stage" name="crm_stage" required>@foreach($crmStages as $value => $label)<option value="{{ $value }}" @selected(old('crm_stage', 'new') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label required" for="payment_status">Оплата</label><select class="form-select" id="payment_status" name="payment_status" required>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', 'unpaid') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label" for="assigned_to">Ответственный</label><select class="form-select" id="assigned_to" name="assigned_to"><option value="">Не назначен</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string)old('assigned_to') === (string)$manager->id)>{{ $manager->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="next_contact_at">Следующий контакт</label><input class="form-control" id="next_contact_at" name="next_contact_at" type="datetime-local" value="{{ old('next_contact_at') }}"></div>
            </div>

            <button class="btn btn-gold w-100 py-3" type="submit"><i class="bi bi-check-lg me-1"></i>Создать заявку</button>
        </div>
    </div>
</form>
@endsection
