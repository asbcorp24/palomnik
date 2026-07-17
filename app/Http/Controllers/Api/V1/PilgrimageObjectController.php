<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PilgrimageObjectResource;
use App\Models\PilgrimageObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PilgrimageObjectController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'vicariate' => ['nullable', 'string', 'max:255'],
            'deanery' => ['nullable', 'string', 'max:255'],
            'sanctity' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'vicariate', 'deanery', 'coverMedia', 'sanctities'])
            ->search($validated['q'] ?? null);

        $query->when($validated['type'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('objectType', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $query->when($validated['vicariate'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('vicariate', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $query->when($validated['deanery'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('deanery', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        $query->when($validated['sanctity'] ?? null, function (Builder $query, string $slug) {
            $query->whereHas('sanctities', function (Builder $query) use ($slug) {
                $query->where('slug', $slug);
            });
        });

        if (($validated['sort'] ?? 'name') === 'newest') {
            $query->orderByDesc('published_at')->orderByDesc('id');
        } else {
            $query->orderBy('name');
        }

        return PilgrimageObjectResource::collection(
            $query->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    public function show(PilgrimageObject $pilgrimageObject): PilgrimageObjectResource
    {
        $isScheduledForFuture = $pilgrimageObject->published_at
            && $pilgrimageObject->published_at->isFuture();

        abort_if(! $pilgrimageObject->is_published || $isScheduledForFuture, 404);

        $pilgrimageObject->load([
            'objectType',
            'vicariate',
            'deanery',
            'coverMedia',
            'sanctities',
            'media',
        ]);

        return new PilgrimageObjectResource($pilgrimageObject);
    }
}
