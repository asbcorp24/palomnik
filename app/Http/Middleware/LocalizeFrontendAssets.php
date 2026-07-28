<?php

namespace App\Http\Middleware;

use App\Services\FrontendAssetService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LocalizeFrontendAssets
{
    public function __construct(private FrontendAssetService $assets)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('assets/vendor/*')) {
            $path = substr($request->path(), strlen('assets/vendor/'));

            try {
                return $this->assets->response($path);
            } catch (Throwable $exception) {
                report($exception);

                return response(
                    'Frontend-ресурс временно недоступен. Выполните кэширование ресурсов на сервере.',
                    503,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(mb_strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent($this->assets->localizeHtml($content));
            $response->headers->remove('Content-Length');
        }

        return $response;
    }
}
