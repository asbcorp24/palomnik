<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\PilgrimageObject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'type' => ['nullable', Rule::in(array_keys(CalendarEvent::typeLabels()))],
            'object' => ['nullable', 'integer', 'exists:pilgrimage_objects,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $month = ! empty($filters['month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters['month'].'-01')->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();

        $events = CalendarEvent::query()
            ->published()
            ->with(['pilgrimageObject.objectType', 'pilgrimageRoute', 'trip'])
            ->where(function (Builder $query) use ($monthStart, $monthEnd) {
                $query->whereBetween('starts_at', [$monthStart, $monthEnd])
                    ->orWhere(function (Builder $query) use ($monthStart, $monthEnd) {
                        $query->where('starts_at', '<=', $monthStart)
                            ->where('ends_at', '>=', $monthEnd);
                    })
                    ->orWhereBetween('ends_at', [$monthStart, $monthEnd]);
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['object'] ?? null, fn (Builder $query, int $objectId) => $query->where('pilgrimage_object_id', $objectId))
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhereHas('pilgrimageObject', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy('starts_at')
            ->get();

        $calendarStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $calendarEnd = $monthEnd->endOfWeek(CarbonImmutable::SUNDAY);
        $days = [];

        for ($day = $calendarStart; $day->lte($calendarEnd); $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isToday(),
                'events' => $events->filter(function (CalendarEvent $event) use ($day) {
                    $start = $event->starts_at->copy()->startOfDay();
                    $end = ($event->ends_at ?: $event->starts_at)->copy()->endOfDay();

                    return $day->betweenIncluded($start, $end);
                })->values(),
                'key' => $day->format('Y-m-d'),
            ];
        }

        $objects = PilgrimageObject::query()
            ->published()
            ->whereHas('calendarEvents', fn (Builder $query) => $query->published())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('site.calendar.index', [
            'month' => $month,
            'days' => collect($days),
            'events' => $events,
            'filters' => $filters,
            'types' => CalendarEvent::typeLabels(),
            'objects' => $objects,
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
        ]);
    }

    public function show(CalendarEvent $calendarEvent): View
    {
        abort_unless($calendarEvent->is_published && (! $calendarEvent->published_at || $calendarEvent->published_at->lte(now())), 404);

        $calendarEvent->load(['pilgrimageObject.objectType', 'pilgrimageRoute', 'trip']);

        return view('site.calendar.show', [
            'event' => $calendarEvent,
            'types' => CalendarEvent::typeLabels(),
        ]);
    }

    public function ics(CalendarEvent $calendarEvent): Response
    {
        abort_unless($calendarEvent->is_published && (! $calendarEvent->published_at || $calendarEvent->published_at->lte(now())), 404);

        $calendarEvent->load(['pilgrimageObject', 'pilgrimageRoute']);
        $start = $calendarEvent->starts_at->copy();
        $end = ($calendarEvent->ends_at ?: $calendarEvent->starts_at->copy()->addHour())->copy();
        $location = $calendarEvent->location ?: $calendarEvent->address ?: optional($calendarEvent->pilgrimageObject)->address;
        $description = trim(collect([
            $calendarEvent->short_description,
            $calendarEvent->description,
            $calendarEvent->pilgrimageObject ? 'Объект: '.$calendarEvent->pilgrimageObject->name : null,
            $calendarEvent->pilgrimageRoute ? 'Маршрут: '.$calendarEvent->pilgrimageRoute->title : null,
            route('calendar.show', $calendarEvent),
        ])->filter()->implode("\n\n"));

        if ($calendarEvent->all_day) {
            $dtStart = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $dtEnd = 'DTEND;VALUE=DATE:'.$end->copy()->addDay()->format('Ymd');
        } else {
            $dtStart = 'DTSTART:'.$start->utc()->format('Ymd\THis\Z');
            $dtEnd = 'DTEND:'.$end->utc()->format('Ymd\THis\Z');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Moscow Pilgrim//Calendar//RU',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$calendarEvent->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            $dtStart,
            $dtEnd,
            'SUMMARY:'.$this->escapeIcs($calendarEvent->title),
            'DESCRIPTION:'.$this->escapeIcs($description),
            'LOCATION:'.$this->escapeIcs((string) $location),
            'URL:'.$this->escapeIcs(route('calendar.show', $calendarEvent)),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="event-'.$calendarEvent->slug.'.ics"',
        ]);
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
