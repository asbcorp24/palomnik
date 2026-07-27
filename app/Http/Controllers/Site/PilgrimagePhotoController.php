<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageRoute;
use App\Models\UserMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PilgrimagePhotoController extends Controller
{
    public function index(Request $request): View
    {
        $photos = $request->user()->media()
            ->where('type', 'image')
            ->with(['pilgrimageRoute:id,title,slug', 'pilgrimageObject:id,name,slug'])
            ->latest()
            ->paginate(18);

        return view('site.profile.photos', [
            'photos' => $photos,
            'routes' => $this->routeOptions(),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:30720'],
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'request_publication' => ['nullable', 'boolean'],
        ]);

        $requestPublication = $request->boolean('request_publication');
        $routeId = $this->validatedRouteId($data['pilgrimage_route_id'] ?? null, $requestPublication);
        $file = $request->file('file');
        $path = $file->store('pilgrimage-photos/'.$request->user()->id.'/'.now()->format('Y/m'), 'public');

        $request->user()->media()->create([
            'pilgrimage_route_id' => $routeId,
            'type' => 'image',
            'path' => $path,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $data['description'] ?? null,
            'status' => $requestPublication ? 'pending' : 'private',
            'publication_requested' => $requestPublication,
        ]);

        return back()->with(
            'success',
            $requestPublication
                ? 'Фотография сохранена и отправлена модератору.'
                : 'Фотография сохранена в личной паломнической галерее.'
        );
    }

    public function update(Request $request, UserMedia $photo): RedirectResponse
    {
        $this->ownedPhoto($request, $photo);

        $data = $request->validate([
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $routeId = $this->validatedRouteId($data['pilgrimage_route_id'] ?? null, false);
        $wasPublished = $photo->status === 'published';

        $photo->update([
            'pilgrimage_route_id' => $routeId,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $wasPublished ? 'pending' : $photo->status,
            'publication_requested' => $wasPublished ? true : $photo->publication_requested,
            'moderated_by' => $wasPublished ? null : $photo->moderated_by,
            'moderated_at' => $wasPublished ? null : $photo->moderated_at,
            'published_at' => $wasPublished ? null : $photo->published_at,
            'moderation_notes' => $wasPublished ? null : $photo->moderation_notes,
        ]);

        return back()->with(
            'success',
            $wasPublished
                ? 'Данные изменены. Фотография повторно отправлена на модерацию.'
                : 'Данные фотографии сохранены.'
        );
    }

    public function requestPublication(Request $request, UserMedia $photo): RedirectResponse
    {
        $this->ownedPhoto($request, $photo);

        $data = $request->validate([
            'pilgrimage_route_id' => ['required', 'integer', 'exists:pilgrimage_routes,id'],
        ]);

        $routeId = $this->validatedRouteId($data['pilgrimage_route_id'], true);

        $photo->update([
            'pilgrimage_route_id' => $routeId,
            'publication_requested' => true,
            'status' => 'pending',
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
            'moderation_notes' => null,
        ]);

        return back()->with('success', 'Фотография отправлена на модерацию для публикации.');
    }

    public function withdrawPublication(Request $request, UserMedia $photo): RedirectResponse
    {
        $this->ownedPhoto($request, $photo);

        $photo->update([
            'publication_requested' => false,
            'status' => 'private',
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
            'moderation_notes' => null,
        ]);

        return back()->with('success', 'Фотография снята с публикации и осталась в личной галерее.');
    }

    public function destroy(Request $request, UserMedia $photo): RedirectResponse
    {
        $this->ownedPhoto($request, $photo);

        if ($photo->path) {
            Storage::disk('public')->delete($photo->path);
        }
        $photo->delete();

        return back()->with('success', 'Фотография удалена.');
    }

    public function gallery(Request $request): View
    {
        $filters = $request->validate([
            'route' => ['nullable', 'string', 'max:255'],
        ]);

        $photos = UserMedia::query()
            ->where('type', 'image')
            ->where('publication_requested', true)
            ->where('status', 'published')
            ->with(['user:id,name,avatar_path,vk_avatar_url', 'pilgrimageRoute:id,title,slug', 'pilgrimageObject:id,name,slug'])
            ->when($filters['route'] ?? null, function ($query, string $slug) {
                $query->whereHas('pilgrimageRoute', fn ($routeQuery) => $routeQuery->where('slug', $slug));
            })
            ->latest('published_at')
            ->paginate(24)
            ->withQueryString();

        return view('site.community.photos', [
            'photos' => $photos,
            'routes' => PilgrimageRoute::query()
                ->published()
                ->whereHas('pilgrimagePhotos', fn ($query) => $query
                    ->where('publication_requested', true)
                    ->where('status', 'published'))
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
            'filters' => $filters,
        ]);
    }

    private function ownedPhoto(Request $request, UserMedia $photo): void
    {
        abort_unless($photo->user_id === $request->user()->id && $photo->type === 'image', 403);
    }

    private function validatedRouteId($routeId, bool $required): ?int
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
                'pilgrimage_route_id' => 'Выбранный маршрут не опубликован или недоступен.',
            ]);
        }

        return $route->id;
    }

    private function routeOptions()
    {
        return PilgrimageRoute::query()->published()->orderBy('title')->get(['id', 'title', 'slug']);
    }

    private function statusLabels(): array
    {
        return [
            'private' => 'Только мне',
            'pending' => 'На модерации',
            'published' => 'Опубликовано',
            'rejected' => 'Отклонено',
        ];
    }
}
