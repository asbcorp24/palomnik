<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PointOfInterestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'object_id' => ['nullable', 'integer', 'exists:pilgrimage_objects,id'],
            'category' => ['nullable', Rule::in(array_keys(PointOfInterest::CATEGORIES))],
            'status' => ['nullable', 'in:published,draft'],
        ]);

        $points = PointOfInterest::query()
            ->with(['pilgrimageObject.objectType'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhereHas('pilgrimageObject', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['object_id'] ?? null, fn (Builder $query, int $id) => $query->where('pilgrimage_object_id', $id))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when(($filters['status'] ?? null) === 'published', fn (Builder $query) => $query->where('is_published', true))
            ->when(($filters['status'] ?? null) === 'draft', fn (Builder $query) => $query->where('is_published', false))
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.points-of-interest.index', [
            'points' => $points,
            'objects' => $this->objects(),
            'categories' => PointOfInterest::CATEGORIES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $point = new PointOfInterest();
        if ($request->filled('object_id')) {
            $point->pilgrimage_object_id = (int) $request->query('object_id');
        }

        return $this->formView($point);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');

        $point = PointOfInterest::query()->create($data);

        return redirect()
            ->route('admin.points-of-interest.edit', $point)
            ->with('success', 'Точка интереса создана.');
    }

    public function edit(PointOfInterest $pointOfInterest): View
    {
        return $this->formView($pointOfInterest);
    }

    public function update(Request $request, PointOfInterest $pointOfInterest): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');
        $pointOfInterest->update($data);

        return redirect()
            ->route('admin.points-of-interest.edit', $pointOfInterest)
            ->with('success', 'Точка интереса обновлена.');
    }

    public function destroy(PointOfInterest $pointOfInterest): RedirectResponse
    {
        $objectId = $pointOfInterest->pilgrimage_object_id;
        $pointOfInterest->delete();

        return redirect()
            ->route('admin.points-of-interest.index', ['object_id' => $objectId])
            ->with('success', 'Точка интереса удалена.');
    }

    private function formView(PointOfInterest $point): View
    {
        return view('admin.points-of-interest.form', [
            'point' => $point,
            'objects' => $this->objects(),
            'categories' => PointOfInterest::CATEGORIES,
        ]);
    }

    private function objects()
    {
        return PilgrimageObject::query()
            ->with('objectType')
            ->orderBy('name')
            ->get(['id', 'object_type_id', 'name', 'address', 'latitude', 'longitude']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pilgrimage_object_id' => ['required', 'integer', 'exists:pilgrimage_objects,id'],
            'category' => ['required', Rule::in(array_keys(PointOfInterest::CATEGORIES))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'url', 'max:255'],
            'schedule_text' => ['nullable', 'string', 'max:3000'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }
}
