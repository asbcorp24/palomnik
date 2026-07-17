<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use App\Models\Vicariate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sanctity' => ['nullable', 'string', 'max:255'],
        ]);

        $query = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'vicariate', 'deanery', 'coverMedia', 'sanctities'])
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('objectType', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['vicariate'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('vicariate', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['deanery'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('deanery', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['sanctity'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('sanctities', fn (Builder $query) => $query->where('slug', $slug)))
            ->orderBy('name');

        $objects = $query->get()->map(function (PilgrimageObject $object) {
            return [
                'id' => $object->id,
                'name' => $object->name,
                'type' => optional($object->objectType)->name,
                'type_slug' => optional($object->objectType)->slug,
                'marker_color' => optional($object->objectType)->marker_color ?: '#b08a3e',
                'vicariate' => optional($object->vicariate)->name,
                'deanery' => optional($object->deanery)->name,
                'sanctities' => $object->sanctities->pluck('name')->values(),
                'address' => $object->address,
                'latitude' => (float) $object->latitude,
                'longitude' => (float) $object->longitude,
                'cover' => optional($object->coverMedia)->url,
                'url' => route('objects.show', $object),
            ];
        })->values();

        return view('site.map', [
            'objects' => $objects,
            'filters' => $filters,
            'types' => ObjectType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'vicariates' => Vicariate::query()->orderBy('name')->get(),
            'deaneries' => Deanery::query()->with('vicariate')->orderBy('name')->get(),
            'sanctities' => Sanctity::query()->orderBy('name')->limit(300)->get(),
        ]);
    }
}
