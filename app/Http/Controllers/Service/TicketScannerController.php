<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingCrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketScannerController extends Controller
{
    public function index(): View
    {
        return view('service.tickets.scanner');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $booking = Booking::query()
            ->with(['trip.pilgrimageRoute', 'user', 'checkedInBy', 'participants'])
            ->where('ticket_token', $data['token'])
            ->firstOrFail();

        return response()->json($this->payload($booking));
    }

    public function checkIn(
        Request $request,
        BookingCrmService $service
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'participants' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $booking = Booking::query()
            ->with(['trip.pilgrimageRoute', 'user', 'participants'])
            ->where('ticket_token', $data['token'])
            ->firstOrFail();

        if ($booking->isClosed()) {
            throw ValidationException::withMessages([
                'token' => 'Билет отменён или возвращён.',
            ]);
        }

        if (! $booking->trip) {
            throw ValidationException::withMessages([
                'token' => 'Поездка для билета не найдена.',
            ]);
        }

        $participants = (int) ($data['participants'] ?? $booking->participants_count);
        if ($participants > $booking->participants_count) {
            throw ValidationException::withMessages([
                'participants' => 'Нельзя отметить больше участников, чем указано в билете.',
            ]);
        }

        if ($booking->isCheckedIn()) {
            throw ValidationException::withMessages([
                'token' => 'Билет уже использован '.optional($booking->checked_in_at)->format('d.m.Y H:i').'.',
            ]);
        }

        $booking = $service->markCheckedIn($booking, $participants, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Билет подтверждён. Отмечено участников: '.$booking->checked_in_participants.'.',
                'booking' => $this->payload($booking),
            ]);
        }

        return back()->with('success', 'Билет подтверждён.');
    }

    private function payload(Booking $booking): array
    {
        $trip = $booking->trip;

        return [
            'id' => $booking->id,
            'ticket_code' => $booking->ticket_code,
            'contact_name' => $booking->contact_name,
            'participants_count' => $booking->participants_count,
            'checked_in_participants' => $booking->checked_in_participants,
            'checked_in_at' => optional($booking->checked_in_at)->format('d.m.Y H:i'),
            'checked_in_by' => optional($booking->checkedInBy)->name,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'is_closed' => $booking->isClosed(),
            'is_checked_in' => $booking->isCheckedIn(),
            'trip_title' => optional(optional($trip)->pilgrimageRoute)->title
                ?: optional($trip)->title
                ?: 'Паломническая поездка',
            'starts_at' => optional(optional($trip)->starts_at)->format('d.m.Y H:i'),
            'meeting_point' => optional($trip)->meeting_point,
            'participants' => $booking->participants->map(fn ($participant) => [
                'name' => $participant->full_name,
                'decision_status' => $participant->decision_status,
                'attendance_status' => $participant->attendance_status,
            ])->values(),
        ];
    }
}
