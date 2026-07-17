@extends('site.profile.layout')

@section('title', 'Мои бронирования — Московский паломник')
@section('profile_title', 'Бронирования и билеты')
@section('profile_subtitle', 'Заявки на поездки, статусы оплаты и электронные коды бронирования.')

@section('profile_content')
<div class="profile-card p-0 overflow-hidden">
    @forelse($bookings as $booking)
        @php
            $statusClass = match($booking->status) {
                'confirmed' => 'status-confirmed',
                'cancelled', 'refunded' => 'status-cancelled',
                'completed' => 'status-published',
                default => 'status-pending',
            };
            $paymentClass = match($booking->payment_status) {
                'paid' => 'status-paid',
                'failed', 'refunded' => 'status-failed',
                'pending' => 'status-pending',
                default => 'status-unpaid',
            };
        @endphp
        <article class="p-4 border-bottom">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="small text-secondary mb-1">{{ optional($booking->trip)->starts_at?->format('d.m.Y H:i') ?: 'Дата уточняется' }}</div>
                    <h2 class="h5 mb-2">{{ optional(optional($booking->trip)->pilgrimageRoute)->title ?: optional($booking->trip)->title ?: 'Паломническая поездка' }}</h2>
                    <div class="small text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ optional($booking->trip)->meeting_point ?: 'Место сбора уточняется' }}</div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="small text-secondary">Участников</div>
                    <div class="fw-semibold">{{ $booking->participants_count }}</div>
                    <div class="small text-secondary mt-2">Сумма</div>
                    <div class="fw-semibold">{{ number_format((float)$booking->total_amount, 0, ',', ' ') }} ₽</div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="d-grid gap-2">
                        <span class="status-badge {{ $statusClass }}">{{ $booking->status }}</span>
                        <span class="status-badge {{ $paymentClass }}">{{ $booking->payment_status }}</span>
                    </div>
                </div>
                <div class="col-lg-2 text-lg-end">
                    <div class="small text-secondary">Код билета</div>
                    <div class="fw-bold text-break">{{ $booking->ticket_code ?: '—' }}</div>
                    @if(!in_array($booking->status, ['cancelled', 'completed', 'refunded'], true) && optional($booking->trip)->starts_at?->isFuture())
                        <form class="mt-3" method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Отменить бронирование?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Отменить</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="empty-state">
            <i class="bi bi-ticket-perforated display-4 d-block mb-3"></i>
            <h2 class="h4">Бронирований пока нет</h2>
            <p>Выберите маршрут с открытой датой поездки.</p>
            <a class="btn btn-pm-gold" href="{{ route('routes.index') }}">Каталог маршрутов</a>
        </div>
    @endforelse
</div>

@if($bookings->hasPages())<div class="mt-4">{{ $bookings->links() }}</div>@endif
@endsection
