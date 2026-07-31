<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PointOfInterestResource;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PointOfInterestController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(PointOfInterest::CATEGORIES))],
            'object' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PointOfInterest::query()
            ->published()
            ->with('pilgrimageObject')
            ->whereHas('pilgrimageObject', fn (Builder $query) => $query->published())
            ->when($validated['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhereHas('pilgrimageObject', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($validated['object'] ?? null, function (Builder $query, string $object) {
                $query->whereHas('pilgrimageObject', function (Builder $query) use ($object) {
                    $query->where('slug', $object);
                    if (ctype_digit($object)) {
                        $query->orWhereKey((int) $object);
                    }
                });
            })
            ->ordered();

        return PointOfInterestResource::collection(
            $query->paginate($validated['per_page'] ?? 50)->withQueryString()
        );
    }

    public function show(PointOfInterest $pointOfInterest): PointOfInterestResource
    {
        abort_if(! $pointOfInterest->is_published, 404);
        abort_unless(
            $pointOfInterest->pilgrimageObject()->published()->exists(),
            404
        );

        $pointOfInterest->load('pilgrimageObject');

        return new PointOfInterestResource($pointOfInterest);
    }
}
