<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Services\MapViewportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MapObjectBySlugController extends Controller
{
    public function __invoke(
        Request $request,
        MapViewportService $map,
        string $slug
    ): Response {
        $objectId = PilgrimageObject::query()
            ->published()
            ->where('slug', $slug)
            ->value('id');

        abort_unless($objectId, 404, 'Объект не найден или не опубликован.');

        $payload = ['data' => $map->objectDetail((int) $objectId)];
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        $etag = '"'.sha1((string) $json).'"';
        $headers = [
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
            'ETag' => $etag,
            'Vary' => 'Accept-Encoding',
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response($json, 200, $headers + [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
