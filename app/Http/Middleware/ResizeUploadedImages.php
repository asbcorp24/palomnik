<?php

namespace App\Http\Middleware;

use App\Services\ImageResizeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ResizeUploadedImages
{
    public function __construct(private ImageResizeService $imageResizeService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->resizeFiles($request->allFiles());

        return $next($request);
    }

    private function resizeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file)) {
                $this->resizeFiles($file);
                continue;
            }

            if ($file instanceof UploadedFile) {
                $this->imageResizeService->resize($file);
            }
        }
    }
}
