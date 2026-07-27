<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageRoute;
use App\Models\UserMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PilgrimagePhotoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->media()
            ->where('type', 'image')
            ->with(['pilgrimageObject:id,name,slug', 'pilgrimageRoute:id,title,slug'])
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => collect($items->items())
                ->map(fn (UserMedia $photo) => $this->photoData($photo))
                ->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:30720'],
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'request_publication' => ['nullable', 'boolean'],
        ]);

        $requestPublication = $request->boolean('request_publication');
        $routeId = $this->routeId($data['pilgrimage_route_id'] ?? null, $requestPublication);
        $file = $request->file('file');
        $path = $file->store('pilgrimage-photos/'.$request->user()->id.'/'.now()->format('Y/m'), 'public');

        $photo = UserMedia::query()->create([
            'user_id' => $request->user()->id,
            'pilgrimage_route_id' => $routeId,
            'type' => 'image',
            'path' => $path,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => $requestPublication ? 'pending' : 'private',
            'publication_requested' => $requestPublication,
        ]);

        return response()->json([
            'data' => $this->photoData($photo->load(['pilgrimageObject', 'pilgrimageRoute'])),
        ], 201);
    }

    public function requestPublication(Request $request, UserMedia $media): JsonResponse
    {
        $this->ownedPhoto($request, $media);

        $data = $request->validate([
            'pilgrimage_route_id' => ['required', 'integer', 'exists:pilgrimage_routes,id'],
        ]);

        $media->update([
            'pilgrimage_route_id' => $this->routeId($data['pilgrimage_route_id'], true),
            'publication_requested' => true,
            'status' => 'pending',
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
            'moderation_notes' => null,
        ]);

        return response()->json([
            'data' => $this->photoData($media->fresh()->load(['pilgrimageObject', 'pilgrimageRoute'])),
        ]);
    }

    public function withdrawPublication(Request $request, UserMedia $media): JsonResponse
    {
        $this->ownedPhoto($request, $media);

        $media->update([
            'publication_requested' => false,
            'status' => 'private',
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
            'moderation_notes' => null,
        ]);

        return response()->json([
            'data' => $this->photoData($media->fresh()->load(['pilgrimageObject', 'pilgrimageRoute'])),
        ]);
    }

    public function destroy(Request $request, UserMedia $media): JsonResponse
    {
        $this->ownedPhoto($request, $media);

        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();

        return response()->json(['deleted' => true]);
    }

    private function ownedPhoto(Request $request, UserMedia $media): void
    {
        abort_unless($media->user_id === $request->user()->id && $media->type === 'image', 403);
    }

    private function routeId($routeId, bool $required): ?int
    {
        if (! $routeId) {
            if ($required) {
                throw ValidationException::withMessages([
                    'pilgrimage_route_id' => 'Для публикации выберите паломнический маршрут.',
                ]);
            }

            return null;
        }

        $route = PilgrimageRoute::query()->published()->find($routeId);
        if (! $route) {
            throw ValidationException::withMessages([
                'pilgrimage_route_id' => 'Выбранный маршрут недоступен.',
            ]);
        }

        return $route->id;
    }

    private function photoData(UserMedia $photo): array
    {
        return [
            'id' => $photo->id,
            'type' => $photo->type,
            'url' => $photo->url,
            'title' => $photo->title,
            'description' => $photo->description,
            'latitude' => $photo->latitude !== null ? (float) $photo->latitude : null,
            'longitude' => $photo->longitude !== null ? (float) $photo->longitude : null,
            'status' => $photo->status,
            'publication_requested' => (bool) $photo->publication_requested,
            'moderation_notes' => $photo->moderation_notes,
            'route' => $photo->pilgrimageRoute ? [
                'id' => $photo->pilgrimageRoute->id,
                'title' => $photo->pilgrimageRoute->title,
                'slug' => $photo->pilgrimageRoute->slug,
            ] : null,
            'object' => $photo->pilgrimageObject ? [
                'id' => $photo->pilgrimageObject->id,
                'name' => $photo->pilgrimageObject->name,
                'slug' => $photo->pilgrimageObject->slug,
            ] : null,
            'created_at' => optional($photo->created_at)->toIso8601String(),
        ];
    }
}
