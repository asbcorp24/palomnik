<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageResizeService
{
    /**
     * Resize an uploaded image in place when it exceeds the configured bounds.
     * Smaller images are left untouched and are never enlarged.
     */
    public function resize(UploadedFile $file): void
    {
        if (! (bool) config('palomnik.images.resize_enabled', true) || ! $file->isValid()) {
            return;
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return;
        }

        $imageInfo = @getimagesize($path);
        if (! is_array($imageInfo)) {
            return;
        }

        $mime = (string) ($imageInfo['mime'] ?? $file->getMimeType() ?? '');
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return;
        }

        // Animated GIFs are preserved as-is because GD would keep only the first frame.
        if ($mime === 'image/gif' && $this->isAnimatedGif($path)) {
            return;
        }

        $maxWidth = max(1, (int) config('palomnik.images.max_width', 1920));
        $maxHeight = max(1, (int) config('palomnik.images.max_height', 1080));
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if ($width < 1 || $height < 1 || ($width <= $maxWidth && $height <= $maxHeight)) {
            return;
        }

        $source = $this->createImage($path, $mime);
        if (! $source) {
            throw new RuntimeException('Не удалось открыть загруженное изображение для уменьшения.');
        }

        try {
            if ($mime === 'image/jpeg') {
                $source = $this->applyExifOrientation($source, $path);
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min($maxWidth / $width, $maxHeight / $height, 1);
            $targetWidth = max(1, (int) floor($width * $scale));
            $targetHeight = max(1, (int) floor($height * $scale));

            if ($targetWidth === $width && $targetHeight === $height) {
                return;
            }

            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (! $target) {
                throw new RuntimeException('Не удалось выделить память для уменьшения изображения.');
            }

            try {
                $this->prepareTransparency($target, $mime);

                if (! imagecopyresampled(
                    $target,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $width,
                    $height
                )) {
                    throw new RuntimeException('Не удалось пропорционально уменьшить изображение.');
                }

                $temporaryPath = tempnam(dirname($path), 'palomnik-image-');
                if ($temporaryPath === false) {
                    throw new RuntimeException('Не удалось создать временный файл изображения.');
                }

                try {
                    $this->saveImage($target, $temporaryPath, $mime);

                    if (! copy($temporaryPath, $path)) {
                        throw new RuntimeException('Не удалось заменить загруженное изображение уменьшенной версией.');
                    }
                } finally {
                    @unlink($temporaryPath);
                }
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }

    private function createImage(string $path, string $mime)
    {
        $function = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            'image/gif' => 'imagecreatefromgif',
            default => null,
        };

        if (! $function || ! function_exists($function)) {
            throw new RuntimeException('Для обработки '.$mime.' требуется соответствующая поддержка в расширении GD.');
        }

        return @$function($path);
    }

    private function saveImage($image, string $path, string $mime): void
    {
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg(
                $image,
                $path,
                max(1, min(100, (int) config('palomnik.images.jpeg_quality', 85)))
            ),
            'image/png' => imagepng(
                $image,
                $path,
                max(0, min(9, (int) config('palomnik.images.png_compression', 8)))
            ),
            'image/webp' => function_exists('imagewebp') && imagewebp(
                $image,
                $path,
                max(1, min(100, (int) config('palomnik.images.webp_quality', 85)))
            ),
            'image/gif' => imagegif($image, $path),
            default => false,
        };

        if (! $saved) {
            throw new RuntimeException('Не удалось сохранить уменьшенное изображение.');
        }
    }

    private function prepareTransparency($image, string $mime): void
    {
        if (! in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }

    private function applyExifOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = null;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $rotated = imagerotate($image, 180, 0);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $rotated = imagerotate($image, -90, 0);
                if ($rotated) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 6:
                $rotated = imagerotate($image, -90, 0);
                break;
            case 7:
                $rotated = imagerotate($image, 90, 0);
                if ($rotated) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 8:
                $rotated = imagerotate($image, 90, 0);
                break;
        }

        if ($rotated) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    private function isAnimatedGif(string $path): bool
    {
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            return false;
        }

        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $contents) > 1;
    }
}
