<?php

namespace Tests\Feature\Api;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctityImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_sanctities_have_images_in_directory_and_object_api(): void
    {
        $this->seed(DemoSeeder::class);

        $directory = $this->getJson('/api/v1/directories/sanctities');
        $directory->assertOk()->assertJsonPath('data.0.image_url', fn ($value) => is_string($value) && str_contains($value, '/storage/demo/'));

        $object = $this->getJson('/api/v1/objects/christ-saviour-cathedral');
        $object->assertOk()->assertJsonPath('data.sanctities.0.image_url', fn ($value) => is_string($value) && str_contains($value, '/storage/demo/'));
    }
}
