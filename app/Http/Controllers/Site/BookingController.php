<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\BookingCrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(
        Request $request,
        Trip $trip,
        BookingCrmService $service
    ): RedirectResponse {
        $data = $request->validate([
            'participants_count' => ['required', 'integer', 'min:1', 'max:10'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        $trip->load('pilgrimageRoute');

        if ($trip->status !== 'open') {
            throw ValidationException::withMessages(['trip' => 'Запись на эту поездку закрыта.']);
        }

        if ($trip->starts_at->isPast()) {
            throw ValidationException::withMessages(['trip' => 'Дата поездки уже прошла.']);
        }

        $hasActiveBooking = Booking::query()
            ->where('trip_id', $trip->id)
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', BookingCrmService::CLOSED_BOOKING_STATUSES)
            ->exists();

        if ($hasActiveBooking) {
            throw ValidationException::withMessages([
                'trip' => 'У вас уже есть активное бронирование на эту поездку.',
            ]);
        }

        $participants = (int) $data['participants_count'];
        $unitPrice = $trip->price !== null
            ? (float) $trip->price
            : (float) ($trip->pilgrimageRoute->base_price ?? 0);

        $booking = $service->createBooking($trip, [
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'participants_count' => $participants,
            'total_amount' => $unitPrice * $participants,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'crm_stage' => 'new',
            'priority' => 'normal',
            'source' => 'site',
            'notes' => $data['notes'] ?? null,
        ], $request->user(), $request->user());

        return redirect()
            ->route('profile.bookings')
            ->with('success', 'Заявка создана. Код бронирования: '.$booking->ticket_code.'.');
    }

    public function cancel(
        Request $request,
        Booking $booking,
        BookingCrmService $service
    ): RedirectResponse {
        abort_unless($booking->user_id === $request->user()->id, 403);

        if (in_array($booking->status, ['cancelled', 'completed', 'refunded'], true)) {
            return back()->with('error', 'Бронирование уже закрыто.');
        }

        if ($booking->trip?->starts_at?->isPast()) {
            throw ValidationException::withMessages([
                'booking' => 'Нельзя отменить прошедшую поездку.',
            ]);
        }

        $service->updateBooking($booking, [
            'status' => 'cancelled',
            'crm_stage' => 'closed',
            'cancellation_reason' => 'Отменено пользователем через личный кабинет.',
        ], $request->user());

        return back()->with('success', 'Бронирование отменено.');
    }
}
