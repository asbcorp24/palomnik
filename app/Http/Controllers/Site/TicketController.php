<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function show(Request $request, Booking $booking): View
    {
        $this->authorizeAccess($request, $booking);
        $booking->load(['trip.pilgrimageRoute', 'user', 'checkedInBy']);

        return view('site.tickets.show', [
            'booking' => $booking,
            'qrPayload' => 'MP-TICKET:'.$booking->ticket_token,
        ]);
    }

    public function ics(Request $request, Booking $booking): Response
    {
        $this->authorizeAccess($request, $booking);
        $booking->load('trip.pilgrimageRoute');
        abort_unless($booking->trip, 404);

        $trip = $booking->trip;
        $start = $trip->starts_at->copy();
        $end = ($trip->ends_at ?: $trip->starts_at->copy()->addHours(8))->copy();
        $title = optional($trip->pilgrimageRoute)->title ?: $trip->title ?: 'Паломническая поездка';
        $description = 'Билет: '.$booking->ticket_code."\n".
            'Участников: '.$booking->participants_count."\n".
            route('tickets.show', $booking);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Moscow Pilgrim//Ticket//RU',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-'.$booking->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escapeIcs($title),
            'DESCRIPTION:'.$this->escapeIcs($description),
            'LOCATION:'.$this->escapeIcs((string) $trip->meeting_point),
            'URL:'.$this->escapeIcs(route('tickets.show', $booking)),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ticket-'.$booking->ticket_code.'.ics"',
        ]);
    }

    private function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();
        abort_unless($user && ($booking->user_id === $user->id || $user->isAdmin() || $user->canManageObjects()), 403);
    }

    private function escapeIcs(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\n", ',', ';'],
            ["\\\\", '\\n', '\\n', '\\,', '\\;'],
            $value
        );
    }
}
