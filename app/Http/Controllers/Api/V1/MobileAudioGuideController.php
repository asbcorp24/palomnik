<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AudioGuide;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use Illuminate\Http\JsonResponse;

class MobileAudioGuideController extends Controller
{
    public function index(): JsonResponse
    {
        $objects = PilgrimageObject::query()
            ->published()
            ->whereHas('audioGuide')
            ->with(['audioGuide', 'objectType', 'coverMedia'])
            ->orderBy('name')
            ->get()
            ->map(fn (PilgrimageObject $object): array => [
                'kind' => 'object',
                'id' => (int) $object->id,
                'slug' => $object->slug,
                'title' => $object->name,
                'subtitle' => optional($object->objectType)->name,
                'cover_url' => optional($object->coverMedia)->url,
                'audio_guide' => $this->guideData($object->audioGuide),
            ]);

        $routes = PilgrimageRoute::query()
            ->published()
            ->whereHas('audioGuide')
            ->with('audioGuide')
            ->orderBy('title')
            ->get()
            ->map(fn (PilgrimageRoute $route): array => [
                'kind' => 'route',
                'id' => (int) $route->id,
                'slug' => $route->slug,
                'title' => $route->title,
                'subtitle' => 'Паломнический маршрут',
                'cover_url' => $route->cover_url,
                'audio_guide' => $this->guideData($route->audioGuide),
            ]);

        return response()->json([
            'data' => $objects->concat($routes)->values(),
        ]);
    }

    public function object(PilgrimageObject $pilgrimageObject): JsonResponse
    {
        $pilgrimageObject->loadMissing(['objectType', 'audioGuide']);
        $isScheduledForFuture = $pilgrimageObject->published_at
            && $pilgrimageObject->published_at->isFuture();
        $typeIsVisible = $pilgrimageObject->objectType
            && $pilgrimageObject->objectType->is_active
            && $pilgrimageObject->objectType->is_public;

        abort_if(! $pilgrimageObject->is_published || $isScheduledForFuture || ! $typeIsVisible, 404);
        abort_if(! $pilgrimageObject->audioGuide, 404);

        return response()->json([
            'data' => $this->guideData($pilgrimageObject->audioGuide),
        ]);
    }

    public function route(PilgrimageRoute $pilgrimageRoute): JsonResponse
    {
        $isScheduledForFuture = $pilgrimageRoute->published_at
            && $pilgrimageRoute->published_at->isFuture();

        abort_if(! $pilgrimageRoute->is_published || $isScheduledForFuture, 404);

        $pilgrimageRoute->loadMissing('audioGuide');
        abort_if(! $pilgrimageRoute->audioGuide, 404);

        return response()->json([
            'data' => $this->guideData($pilgrimageRoute->audioGuide),
        ]);
    }

    private function guideData(AudioGuide $guide): array
    {
        return [
            'id' => (int) $guide->id,
            'title' => $guide->title ?: 'Аудиогид',
            'url' => $guide->url ? url($guide->url) : null,
            'transcript' => $guide->transcript,
            'mime_type' => $guide->mime_type,
            'size' => (int) $guide->size,
        ];
    }
}
