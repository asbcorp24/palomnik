<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\Sanctity;
use App\Models\Vicariate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function objectTypes(): JsonResponse
    {
        return response()->json([
            'data' => ObjectType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'marker_color', 'icon']),
        ]);
    }

    public function vicariates(): JsonResponse
    {
        return response()->json([
            'data' => Vicariate::query()
                ->with(['deaneries' => function ($query) {
                    $query->orderBy('name')->select(['id', 'vicariate_id', 'name', 'slug']);
                }])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function deaneries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vicariate' => ['nullable', 'string', 'max:255'],
        ]);

        $deaneries = Deanery::query()
            ->with('vicariate:id,name,slug')
            ->when($validated['vicariate'] ?? null, function ($query, string $slug) {
                $query->whereHas('vicariate', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            })
            ->orderBy('name')
            ->get(['id', 'vicariate_id', 'name', 'slug']);

        return response()->json(['data' => $deaneries]);
    }

    public function sanctities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $sanctities = Sanctity::query()
            ->when($validated['q'] ?? null, function ($query, string $term) {
                $query->where('name', 'like', '%'.trim($term).'%');
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'slug', 'type', 'description', 'image_path'])
            ->map(fn (Sanctity $sanctity) => [
                'id' => $sanctity->id,
                'name' => $sanctity->name,
                'slug' => $sanctity->slug,
                'type' => $sanctity->type,
                'description' => $sanctity->description,
                'image_url' => $sanctity->image_url,
            ]); 

        return response()->json(['data' => $sanctities]);
    }
}
