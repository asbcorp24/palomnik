<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCommunityController extends Controller
{
    public function photos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route' => ['nullable', 'string', 'max:255'],
        ]);

        $photos = UserMedia::query()
            ->where('type', 'image')
            ->where('publication_requested', true)
            ->where('status', 'published')
            ->whereNotNull('path')
            ->with([
                'user:id,name',
                'pilgrimageRoute:id,title,slug',
                'pilgrimageObject:id,name,slug',
            ])
            ->when($validated['route'] ?? null, function ($query, string $slug): void {
                $query->whereHas('pilgrimageRoute', fn ($routeQuery) => $routeQuery->where('slug', $slug));
            })
            ->latest('published_at')
            ->latest('id')
            ->paginate(30);

        return response()->json([
            'data' => collect($photos->items())->map(fn (UserMedia $photo): array => [
                'id' => (int) $photo->id,
                'title' => $photo->title ?: 'Паломническая фотография',
                'description' => $photo->description,
                'url' => $photo->url,
                'published_at' => optional($photo->published_at)->toIso8601String(),
                'author' => $photo->user ? [
                    'id' => (int) $photo->user->id,
                    'name' => $photo->user->name,
                ] : null,
                'route' => $photo->pilgrimageRoute ? [
                    'id' => (int) $photo->pilgrimageRoute->id,
                    'title' => $photo->pilgrimageRoute->title,
                    'slug' => $photo->pilgrimageRoute->slug,
                ] : null,
                'object' => $photo->pilgrimageObject ? [
                    'id' => (int) $photo->pilgrimageObject->id,
                    'name' => $photo->pilgrimageObject->name,
                    'slug' => $photo->pilgrimageObject->slug,
                ] : null,
            ])->values(),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
        ]);
    }
}
