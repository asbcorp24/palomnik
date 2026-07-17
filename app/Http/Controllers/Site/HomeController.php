<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Sanctity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredObjects = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'vicariate', 'deanery', 'coverMedia', 'sanctities'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $types = ObjectType::query()
            ->withCount([
                'pilgrimageObjects as published_objects_count' => function (Builder $query) {
                    $query->published();
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $upcomingEvents = CalendarEvent::query()
            ->published()
            ->upcoming()
            ->with('pilgrimageObject')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        $stats = [
            'objects' => PilgrimageObject::query()->published()->count(),
            'sanctities' => Sanctity::query()->count(),
            'routes' => PilgrimageRoute::query()->published()->count(),
        ];

        return view('site.home', compact('featuredObjects', 'types', 'upcomingEvents', 'stats'));
    }
}
