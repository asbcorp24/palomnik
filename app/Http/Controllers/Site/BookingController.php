<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'participants_count' => ['required', 'integer', 'min:1', 'max:10'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        $booking = DB::transaction(function () use ($request, $trip, $data) {
            $lockedTrip = Trip::query()
                ->with('pilgrimageRoute')
                ->lockForUpdate()
                ->findOrFail($trip->id);

            if ($lockedTrip->status !== 'open') {
                throw ValidationException::withMessages(['trip' => 'Запись на эту поездку закрыта.']);
            }
            if ($lockedTrip->starts_at->isPast()) {
                throw ValidationException::withMessages(['trip' => 'Дата поездки уже прошла.']);
            }

            $hasActiveBooking = Booking::query()
                ->where('trip_id', $lockedTrip->id)
                ->where('user_id', $request->user()->id)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->exists();

            if ($hasActiveBooking) {
                throw ValidationException::withMessages(['trip' => 'У вас уже есть активное бронирование на эту поездку.']);
            }

            $participants = (int) $data['participants_count'];
            if ($lockedTrip->capacity !== null
                && $lockedTrip->booked_count + $participants > $lockedTrip->capacity) {
                throw ValidationException::withMessages(['participants_count' => 'Недостаточно свободных мест.']);
            }

            $unitPrice = $lockedTrip->price !== null
                ? (float) $lockedTrip->price
                : (float) ($lockedTrip->pilgrimageRoute->base_price ?? 0);

            $booking = Booking::query()->create([
                'trip_id' => $lockedTrip->id,
                'user_id' => $request->user()->id,
                'contact_name' => $data['contact_name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
                'participants_count' => $participants,
                'total_amount' => $unitPrice * $participants,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'ticket_code' => $this->ticketCode(),
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedTrip->increment('booked_count', $participants);

            return $booking;
        });

        return redirect()
            ->route('profile.bookings')
            ->with('success', 'Заявка создана. Код бронирования: '.$booking->ticket_code.'.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        if (in_array($booking->status, ['cancelled', 'completed', 'refunded'], true)) {
            return back()->with('error', 'Бронирование уже закрыто.');
        }

        DB::transaction(function () use ($booking) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            if ($trip->starts_at->isPast()) {
                throw ValidationException::withMessages(['booking' => 'Нельзя отменить прошедшую поездку.']);
            }

            $booking->update(['status' => 'cancelled']);
            $trip->booked_count = max(0, $trip->booked_count - $booking->participants_count);
            $trip->save();
        });

        return back()->with('success', 'Бронирование отменено.');
    }

    private function ticketCode(): string
    {
        do {
            $code = 'MP-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Booking::query()->where('ticket_code', $code)->exists());

        return $code;
    }
}
