@extends('site.layouts.app')

@section('title', 'Билет '.$booking->ticket_code.' — Московский паломник')

@push('styles')
<style>
.ticket-sheet{max-width:900px;margin:0 auto;background:#fff;border:1px solid var(--pm-border);border-radius:28px;overflow:hidden;box-shadow:var(--pm-shadow)}
.ticket-head{padding:28px;background:linear-gradient(135deg,var(--pm-green),#18322a);color:#fff}
.ticket-body{padding:28px}.ticket-code{font-family:monospace;font-size:1.15rem;letter-spacing:.08em}.ticket-qr{width:260px;min-height:260px;margin:0 auto;padding:16px;border:1px solid var(--pm-border);border-radius:20px;background:#fff;display:flex;align-items:center;justify-content:center}.ticket-row{padding:14px 0;border-bottom:1px dashed var(--pm-border)}
@media print{body{background:#fff!important;padding:0!important}.site-header,.site-footer,.mobile-bottom-nav,.site-alerts,.no-print{display:none!important}main{padding:0!important}.ticket-sheet{max-width:none;border:0;border-radius:0;box-shadow:none}.ticket-head{-webkit-print-color-adjust:exact;print-color-adjust:exact}.ticket-body{padding:20px}.ticket-qr{break-inside:avoid}}
</style>
@endpush

@section('content')
@php
$statusLabels=['pending'=>'Ожидает подтверждения','confirmed'=>'Подтверждено','cancelled'=>'Отменено','completed'=>'Завершено','refunded'=>'Возвращено'];
$paymentLabels=['unpaid'=>'Не оплачено','pending'=>'Платёж обрабатывается','paid'=>'Оплачено','failed'=>'Ошибка оплаты','refunded'=>'Возвращено'];
$trip=$booking->trip;
$title=optional(optional($trip)->pilgrimageRoute)->title ?: optional($trip)->title ?: 'Паломническая поездка';
@endphp
<section class="page-hero no-print"><div class="container"><nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('profile.bookings') }}">Мои билеты</a></li><li class="breadcrumb-item active">{{ $booking->ticket_code }}</li></ol></nav><div class="d-flex flex-wrap justify-content-between align-items-end gap-3"><div><div class="section-kicker mb-2">Электронный билет</div><h1 class="section-title mb-0">{{ $title }}</h1></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-pm" href="{{ route('tickets.ics',$booking) }}"><i class="bi bi-calendar-plus me-2"></i>В календарь</a><button class="btn btn-pm-gold" type="button" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать / PDF</button></div></div></div></section>

<section class="section-space pt-5"><div class="container"><article class="ticket-sheet">
<div class="ticket-head"><div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><div class="small text-uppercase opacity-75 mb-2">Московский паломник</div><h2 class="h3 mb-2">{{ $title }}</h2><div class="opacity-75">Электронный билет на {{ $booking->participants_count }} {{ trans_choice('участника|участников|участников',$booking->participants_count) }}</div></div><div class="text-md-end"><div class="small opacity-75">Код билета</div><div class="ticket-code fw-bold">{{ $booking->ticket_code }}</div></div></div></div>
<div class="ticket-body"><div class="row g-5 align-items-center"><div class="col-lg-7">
<div class="ticket-row"><div class="small text-secondary">Дата и время</div><div class="fw-semibold">{{ optional($trip)->starts_at?->format('d.m.Y H:i') ?: 'Уточняется' }}@if(optional($trip)->ends_at) — {{ $trip->ends_at->format('d.m.Y H:i') }}@endif</div></div>
<div class="ticket-row"><div class="small text-secondary">Место сбора</div><div class="fw-semibold">{{ optional($trip)->meeting_point ?: 'Уточняется' }}</div></div>
<div class="ticket-row"><div class="small text-secondary">Владелец билета</div><div class="fw-semibold">{{ $booking->contact_name }}</div><div class="small text-secondary">{{ $booking->email }} · {{ $booking->phone }}</div></div>
<div class="ticket-row"><div class="row g-3"><div class="col-6"><div class="small text-secondary">Участников</div><div class="fw-semibold">{{ $booking->participants_count }}</div></div><div class="col-6"><div class="small text-secondary">Сумма</div><div class="fw-semibold">{{ number_format((float)$booking->total_amount,0,',',' ') }} ₽</div></div></div></div>
<div class="ticket-row"><div class="row g-3"><div class="col-6"><div class="small text-secondary">Статус</div><div class="fw-semibold">{{ $statusLabels[$booking->status]??$booking->status }}</div></div><div class="col-6"><div class="small text-secondary">Оплата</div><div class="fw-semibold">{{ $paymentLabels[$booking->payment_status]??$booking->payment_status }}</div></div></div></div>
@if($booking->checked_in_at)<div class="alert alert-success mt-4 mb-0"><i class="bi bi-check-circle-fill me-2"></i>Билет использован {{ $booking->checked_in_at->format('d.m.Y H:i') }}. Отмечено участников: {{ $booking->checked_in_participants }}.</div>@elseif($booking->isClosed())<div class="alert alert-danger mt-4 mb-0"><i class="bi bi-x-circle me-2"></i>Билет недействителен: бронирование закрыто.</div>@else<div class="alert alert-light border mt-4 mb-0"><i class="bi bi-info-circle me-2"></i>Покажите QR-код организатору при посадке или регистрации.</div>@endif
</div><div class="col-lg-5 text-center"><div class="ticket-qr" id="ticketQr"></div><div class="small text-secondary mt-3">QR-код содержит защищённый одноразовый идентификатор билета.</div></div></div>
<div class="d-flex flex-wrap justify-content-between gap-3 mt-5 pt-4 border-top no-print"><a class="btn btn-light" href="{{ route('profile.bookings') }}"><i class="bi bi-arrow-left me-2"></i>Все бронирования</a><div class="small text-secondary align-self-center">При отмене бронирования QR-код автоматически становится недействительным.</div></div>
</div></article></div></section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('ticketQr'), {text:@json($qrPayload),width:228,height:228,correctLevel:QRCode.CorrectLevel.H});
</script>
@endpush
