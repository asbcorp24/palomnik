<?php

namespace Tests\Unit;

use App\Services\ImageResizeService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageResizeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('palomnik.images.resize_enabled', true);
        config()->set('palomnik.images.max_width', 1920);
        config()->set('palomnik.images.max_height', 1080);
        config()->set('palomnik.images.jpeg_quality', 85);
    }

    public function test_large_landscape_image_is_resized_proportionally(): void
    {
        $file = UploadedFile::fake()->image('landscape.jpg', 3000, 2000);

        app(ImageResizeService::class)->resize($file);

        [$width, $height] = getimagesize($file->getRealPath());

        $this->assertSame(1620, $width);
        $this->assertSame(1080, $height);
    }

    public function test_large_portrait_image_is_resized_proportionally(): void
    {
        $file = UploadedFile::fake()->image('portrait.jpg', 2000, 3000);

        app(ImageResizeService::class)->resize($file);

        [$width, $height] = getimagesize($file->getRealPath());

        $this->assertSame(720, $width);
        $this->assertSame(1080, $height);
    }

    public function test_small_image_is_not_enlarged(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 800, 600);

        app(ImageResizeService::class)->resize($file);

        [$width, $height] = getimagesize($file->getRealPath());

        $this->assertSame(800, $width);
        $this->assertSame(600, $height);
    }
}
