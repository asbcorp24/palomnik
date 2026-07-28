<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MapTileCacheService
{
    private const SIZE_INDEX_REFRESH_SECONDS = 300;

    public function response(Request $request, int $z, int $x, int $y): Response
    {
        if (! (bool) config('palomnik.maps.tile_cache_enabled', true)) {
            abort(404);
        }

        $this->validateCoordinates($z, $x, $y);

        $disk = Storage::disk($this->disk());
        $tilePath = $this->tilePath($z, $x, $y);
        $metadataPath = $tilePath.'.json';
        $cached = $this->readCachedTile($disk, $tilePath, $metadataPath);

        if ($cached && $this->isFresh($cached['metadata'])) {
            return $this->localResponse($request, $cached['contents'], $cached['metadata'], 'HIT');
        }

        try {
            $upstreamResponse = $this->requestUpstream($z, $x, $y, $cached['metadata'] ?? []);

            if ($upstreamResponse->status() === 304 && $cached) {
                $metadata = array_merge($cached['metadata'], [
                    'expires_at' => time() + $this->ttlFromResponse($upstreamResponse),
                    'last_checked_at' => time(),
                ]);

                $stored = $this->storeMetadataIfWithinLimit($disk, $metadataPath, $metadata);

                return $this->localResponse(
                    $request,
                    $cached['contents'],
                    $stored ? $metadata : $cached['metadata'],
                    $stored ? 'REVALIDATED' : 'REVALIDATED-LIMIT'
                );
            }

            if ($upstreamResponse->successful()) {
                $contents = $upstreamResponse->body();
                $contentType = strtolower(trim(explode(';', (string) $upstreamResponse->header('Content-Type'))[0]));

                if (! str_starts_with($contentType, 'image/') || strlen($contents) < 64) {
                    throw new \RuntimeException('Сервер карт вернул некорректный файл тайла.');
                }

                $metadata = [
                    'cached_at' => time(),
                    'last_checked_at' => time(),
                    'expires_at' => time() + $this->ttlFromResponse($upstreamResponse),
                    'content_type' => $contentType,
                    'upstream_etag' => $upstreamResponse->header('ETag'),
                    'upstream_last_modified' => $upstreamResponse->header('Last-Modified'),
                    'source' => $this->upstreamUrl($z, $x, $y),
                ];

                $stored = $this->storeTileIfWithinLimit(
                    $disk,
                    $tilePath,
                    $metadataPath,
                    $contents,
                    $metadata
                );

                return $this->localResponse(
                    $request,
                    $contents,
                    $metadata,
                    $stored ? ($cached ? 'REFRESH' : 'MISS') : 'BYPASS-LIMIT'
                );
            }

            if ($cached) {
                Log::warning('OpenStreetMap tile refresh failed; serving stale tile.', [
                    'z' => $z,
                    'x' => $x,
                    'y' => $y,
                    'status' => $upstreamResponse->status(),
                ]);

                return $this->localResponse($request, $cached['contents'], $cached['metadata'], 'STALE');
            }

            return response('', $upstreamResponse->status() === 404 ? 404 : 503, [
                'Cache-Control' => 'no-store',
                'X-Map-Tile-Cache' => 'ERROR',
            ]);
        } catch (Throwable $exception) {
            if ($cached) {
                Log::warning('OpenStreetMap tile request failed; serving stale tile.', [
                    'z' => $z,
                    'x' => $x,
                    'y' => $y,
                    'message' => $exception->getMessage(),
                ]);

                return $this->localResponse($request, $cached['contents'], $cached['metadata'], 'STALE');
            }

            report($exception);

            return response('', 503, [
                'Cache-Control' => 'no-store',
                'Retry-After' => '60',
                'X-Map-Tile-Cache' => 'ERROR',
            ]);
        }
    }

    private function requestUpstream(int $z, int $x, int $y, array $metadata): HttpResponse
    {
        $headers = [
            'User-Agent' => $this->userAgent(),
            'Accept' => 'image/png,image/*;q=0.9,*/*;q=0.1',
        ];

        if (! empty($metadata['upstream_etag'])) {
            $headers['If-None-Match'] = (string) $metadata['upstream_etag'];
        }

        if (! empty($metadata['upstream_last_modified'])) {
            $headers['If-Modified-Since'] = (string) $metadata['upstream_last_modified'];
        }

        return Http::withHeaders($headers)
            ->connectTimeout(max(2, (int) config('palomnik.maps.tile_connect_timeout', 5)))
            ->timeout(max(5, (int) config('palomnik.maps.tile_timeout', 15)))
            ->get($this->upstreamUrl($z, $x, $y));
    }

    private function localResponse(Request $request, string $contents, array $metadata, string $cacheStatus): Response
    {
        $etag = '"'.sha1($contents).'"';
        $browserTtl = max(60, (int) config('palomnik.maps.tile_browser_ttl', 86400));
        $headers = [
            'Content-Type' => (string) ($metadata['content_type'] ?? 'image/png'),
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'public, max-age='.$browserTtl,
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'X-Map-Tile-Cache' => $cacheStatus,
        ];

        if (! empty($metadata['cached_at'])) {
            $headers['Age'] = (string) max(0, time() - (int) $metadata['cached_at']);
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', (int) $metadata['cached_at']).' GMT';
        }

        $ifNoneMatch = (string) $request->header('If-None-Match', '');
        if ($ifNoneMatch !== '' && str_contains($ifNoneMatch, $etag)) {
            unset($headers['Content-Length']);

            return response('', 304, $headers);
        }

        return response($contents, 200, $headers);
    }

    private function ttlFromResponse(HttpResponse $response): int
    {
        $cacheControl = (string) $response->header('Cache-Control');
        if (preg_match('/(?:s-maxage|max-age)\s*=\s*(\d+)/i', $cacheControl, $matches)) {
            return max(60, (int) $matches[1]);
        }

        $expires = strtotime((string) $response->header('Expires'));
        if ($expires !== false && $expires > time()) {
            return max(60, $expires - time());
        }

        return max(604800, (int) config('palomnik.maps.tile_default_ttl', 604800));
    }

    private function isFresh(array $metadata): bool
    {
        return (int) ($metadata['expires_at'] ?? 0) > time();
    }

    private function readCachedTile($disk, string $tilePath, string $metadataPath): ?array
    {
        if (! $disk->exists($tilePath)) {
            return null;
        }

        $contents = $disk->get($tilePath);
        if ($contents === '') {
            return null;
        }

        $metadata = [];
        if ($disk->exists($metadataPath)) {
            $decoded = json_decode($disk->get($metadataPath), true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        return [
            'contents' => $contents,
            'metadata' => $metadata,
        ];
    }

    private function storeTileIfWithinLimit(
        $disk,
        string $tilePath,
        string $metadataPath,
        string $contents,
        array $metadata
    ): bool {
        $metadataContents = $this->metadataContents($metadata);
        $currentSize = $this->currentCacheSize($disk);
        $replacedSize = $this->existingFileSize($disk, $tilePath)
            + $this->existingFileSize($disk, $metadataPath);
        $projectedSize = max(0, $currentSize - $replacedSize)
            + strlen($contents)
            + strlen($metadataContents);

        if (! $this->fitsCacheLimit($projectedSize)) {
            return false;
        }

        $disk->put($tilePath, $contents);
        $disk->put($metadataPath, $metadataContents);
        $this->writeSizeIndex($disk, $projectedSize);

        return true;
    }

    private function storeMetadataIfWithinLimit($disk, string $metadataPath, array $metadata): bool
    {
        $metadataContents = $this->metadataContents($metadata);
        $currentSize = $this->currentCacheSize($disk);
        $projectedSize = max(0, $currentSize - $this->existingFileSize($disk, $metadataPath))
            + strlen($metadataContents);

        if (! $this->fitsCacheLimit($projectedSize)) {
            return false;
        }

        $disk->put($metadataPath, $metadataContents);
        $this->writeSizeIndex($disk, $projectedSize);

        return true;
    }

    private function currentCacheSize($disk): int
    {
        $indexPath = $this->sizeIndexPath();

        if ($disk->exists($indexPath)) {
            $index = json_decode($disk->get($indexPath), true);
            if (
                is_array($index)
                && isset($index['bytes'], $index['calculated_at'])
                && (int) $index['calculated_at'] >= time() - self::SIZE_INDEX_REFRESH_SECONDS
            ) {
                return max(0, (int) $index['bytes']);
            }
        }

        $size = 0;
        foreach ($disk->allFiles($this->cacheDirectory()) as $path) {
            if ($path === $indexPath) {
                continue;
            }

            try {
                $size += max(0, (int) $disk->size($path));
            } catch (Throwable $exception) {
                Log::debug('Unable to calculate map tile cache file size.', [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->writeSizeIndex($disk, $size);

        return $size;
    }

    private function writeSizeIndex($disk, int $bytes): void
    {
        $disk->put($this->sizeIndexPath(), json_encode([
            'bytes' => max(0, $bytes),
            'calculated_at' => time(),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function existingFileSize($disk, string $path): int
    {
        if (! $disk->exists($path)) {
            return 0;
        }

        try {
            return max(0, (int) $disk->size($path));
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function fitsCacheLimit(int $projectedSize): bool
    {
        $maxSizeMb = (int) config('palomnik.maps.tile_cache_max_size_mb', 1024);
        if ($maxSizeMb <= 0) {
            return true;
        }

        return $projectedSize <= $maxSizeMb * 1024 * 1024;
    }

    private function metadataContents(array $metadata): string
    {
        return (string) json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function sizeIndexPath(): string
    {
        return $this->cacheDirectory().'/.cache-size.json';
    }

    private function cacheDirectory(): string
    {
        return trim((string) config('palomnik.maps.tile_cache_directory', 'map-tiles/osm'), '/');
    }

    private function validateCoordinates(int $z, int $x, int $y): void
    {
        $maxZoom = max(0, min(22, (int) config('palomnik.maps.tile_max_zoom', 19)));
        if ($z < 0 || $z > $maxZoom) {
            abort(404);
        }

        $maximumCoordinate = (1 << $z) - 1;
        if ($x < 0 || $y < 0 || $x > $maximumCoordinate || $y > $maximumCoordinate) {
            abort(404);
        }
    }

    private function upstreamUrl(int $z, int $x, int $y): string
    {
        $template = (string) config('palomnik.maps.raster_tiles', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');

        return strtr($template, [
            '{z}' => (string) $z,
            '{x}' => (string) $x,
            '{y}' => (string) $y,
        ]);
    }

    private function tilePath(int $z, int $x, int $y): string
    {
        return $this->cacheDirectory().'/'.$z.'/'.$x.'/'.$y.'.png';
    }

    private function disk(): string
    {
        return (string) config('palomnik.maps.tile_cache_disk', 'local');
    }

    private function userAgent(): string
    {
        $configured = trim((string) config('palomnik.maps.tile_user_agent'));
        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $contact = trim((string) config('mail.from.address'));
        $details = array_filter([$appUrl, $contact]);

        return 'MoscowPilgrimTileCache/1.0 ('.implode('; ', $details).')';
    }
}
