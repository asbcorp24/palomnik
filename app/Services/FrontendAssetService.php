<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FrontendAssetService
{
    public function localUrl(string $path): string
    {
        return '/assets/vendor/'.ltrim($path, '/');
    }

    public function localizeHtml(string $html): string
    {
        foreach ((array) config('frontend_assets.replacements', []) as $externalUrl => $path) {
            $html = str_replace($externalUrl, $this->localUrl((string) $path), $html);
        }

        // The design already has safe system-font fallbacks. Removing these
        // links prevents any stylesheet or font request to Google domains.
        $html = preg_replace(
            '~<link\b[^>]*(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^>]*>\s*~i',
            '',
            $html
        ) ?? $html;

        return $html;
    }

    public function response(string $requestedPath): Response
    {
        $path = $this->normalizePath($requestedPath);
        $asset = $this->asset($path);
        $contents = $this->contents($path, $asset);

        return response($contents, 200, [
            'Content-Type' => (string) ($asset['content_type'] ?? 'application/octet-stream'),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function cacheAll(bool $refresh = false): array
    {
        $results = [];

        foreach (array_keys((array) config('frontend_assets.assets', [])) as $path) {
            $path = (string) $path;
            $cachePath = $this->cachePath($path);

            if ($refresh) {
                Storage::disk($this->disk())->delete($cachePath);
            }

            $this->contents($path, $this->asset($path));
            $results[$path] = Storage::disk($this->disk())->path($cachePath);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $asset
     */
    private function contents(string $path, array $asset): string
    {
        $disk = Storage::disk($this->disk());
        $cachePath = $this->cachePath($path);

        if ($disk->exists($cachePath)) {
            $cached = $disk->get($cachePath);
            if ($cached !== '') {
                return $cached;
            }
        }

        try {
            $response = $this->http()->get((string) $asset['url']);
            $response->throw();
            $contents = $response->body();

            if (strlen($contents) < 50) {
                throw new RuntimeException('Получен пустой или повреждённый файл: '.$path);
            }

            $disk->put($cachePath, $contents);

            return $contents;
        } catch (Throwable $exception) {
            Log::error('Не удалось загрузить локальный frontend-ресурс.', [
                'path' => $path,
                'url' => $asset['url'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Frontend-ресурс временно недоступен. Выполните php artisan frontend-assets:cache.',
                0,
                $exception
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(string $path): array
    {
        $assets = (array) config('frontend_assets.assets', []);
        $asset = $assets[$path] ?? null;

        if (! is_array($asset) || empty($asset['url'])) {
            abort(404);
        }

        return $asset;
    }

    private function normalizePath(string $path): string
    {
        $path = rawurldecode(trim($path, '/'));

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            abort(404);
        }

        return $path;
    }

    private function cachePath(string $path): string
    {
        $directory = trim((string) config('frontend_assets.directory', 'frontend-assets'), '/');

        return $directory.'/'.$path;
    }

    private function disk(): string
    {
        return (string) config('frontend_assets.disk', 'local');
    }

    private function http(): PendingRequest
    {
        return Http::timeout(max(5, (int) config('frontend_assets.timeout', 45)))
            ->connectTimeout(15)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'MoscowPilgrimAssetCache/1.0',
                'Accept' => '*/*',
            ]);
    }
}
