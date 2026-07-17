<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Vicariate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,newest'],
        ]);

        $objects = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'vicariate', 'deanery', 'coverMedia', 'sanctities'])
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('objectType', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['vicariate'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('vicariate', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($filters['deanery'] ?? null, function (Builder $query, string $slug) {
                $query->whereHas('deanery', fn (Builder $query) => $query->where('slug', $slug));
            });

        if (($filters['sort'] ?? 'name') === 'newest') {
            $objects->orderByDesc('published_at')->orderByDesc('id');
        } else {
            $objects->orderBy('name');
        }

        return view('site.objects.index', [
            'objects' => $objects->paginate(12)->withQueryString(),
            'types' => ObjectType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'vicariates' => Vicariate::query()->orderBy('name')->get(),
            'deaneries' => Deanery::query()->with('vicariate')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(PilgrimageObject $object): View
    {
        $isScheduledForFuture = $object->published_at && $object->published_at->isFuture();
        abort_if(! $object->is_published || $isScheduledForFuture, 404);

        $object->load([
            'objectType',
            'vicariate',
            'deanery',
            'coverMedia',
            'sanctities',
            'media',
        ]);

        $similarObjects = PilgrimageObject::query()
            ->published()
            ->where('object_type_id', $object->object_type_id)
            ->where('id', '<>', $object->id)
            ->with(['objectType', 'coverMedia'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('site.objects.show', compact('object', 'similarObjects'));
    }
}
