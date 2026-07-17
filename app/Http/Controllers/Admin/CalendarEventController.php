<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(CalendarEvent::typeLabels()))],
            'status' => ['nullable', Rule::in(['published', 'draft', 'past'])],
        ]);

        $events = CalendarEvent::query()
            ->with(['pilgrimageObject', 'pilgrimageRoute', 'trip'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhereHas('pilgrimageObject', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(($filters['status'] ?? null) === 'published', fn (Builder $query) => $query->where('is_published', true))
            ->when(($filters['status'] ?? null) === 'draft', fn (Builder $query) => $query->where('is_published', false))
            ->when(($filters['status'] ?? null) === 'past', fn (Builder $query) => $query->where('starts_at', '<', now()))
            ->orderByRaw('CASE WHEN starts_at >= CURRENT_TIMESTAMP THEN 0 ELSE 1 END')
            ->orderBy('starts_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.calendar.index', [
            'events' => $events,
            'filters' => $filters,
            'types' => CalendarEvent::typeLabels(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new CalendarEvent());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->transform($request, $data);
        $data['created_by'] = $request->user()->id;

        $event = CalendarEvent::query()->create($data);

        return redirect()->route('admin.calendar.edit', $event)
            ->with('success', 'Событие создано.');
    }

    public function edit(CalendarEvent $calendarEvent): View
    {
        return $this->form($calendarEvent);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $data = $this->validated($request, $calendarEvent);
        $calendarEvent->update($this->transform($request, $data, $calendarEvent));

        return redirect()->route('admin.calendar.edit', $calendarEvent)
            ->with('success', 'Событие обновлено.');
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        $calendarEvent->delete();

        return redirect()->route('admin.calendar.index')->with('success', 'Событие удалено.');
    }

    private function form(CalendarEvent $event): View
    {
        return view('admin.calendar.form', [
            'event' => $event,
            'types' => CalendarEvent::typeLabels(),
            'objects' => PilgrimageObject::query()->orderBy('name')->get(['id', 'name', 'address']),
            'routes' => PilgrimageRoute::query()->orderBy('title')->get(['id', 'title']),
            'trips' => Trip::query()->with('pilgrimageRoute')->orderByDesc('starts_at')->limit(500)->get(),
        ]);
    }

    private function validated(Request $request, ?CalendarEvent $event = null): array
    {
        $slugRule = Rule::unique('calendar_events', 'slug');
        if ($event) {
            $slugRule->ignore($event->id);
        }

        return $request->validate([
            'pilgrimage_object_id' => ['nullable', 'integer', 'exists:pilgrimage_objects,id'],
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'type' => ['required', Rule::in(array_keys(CalendarEvent::typeLabels()))],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_url' => ['nullable', 'url', 'max:2048'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function transform(Request $request, array $data, ?CalendarEvent $event = null): array
    {
        $base = Str::slug($data['slug'] ?: $data['title']) ?: 'event';
        $data['slug'] = $this->uniqueSlug($base, $event?->id);
        $data['all_day'] = $request->boolean('all_day');
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published']
            ? ($data['published_at'] ?? $event?->published_at ?? now())
            : null;

        return $data;
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $index = 2;

        while (CalendarEvent::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }
}
