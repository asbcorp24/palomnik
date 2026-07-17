<?php

namespace Tests\Feature\Api;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilgrimageObjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', 'v1');
    }

    public function test_objects_endpoint_returns_only_published_objects(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#B08A3E',
            'sort_order' => 10,
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Опубликованный храм',
            'slug' => 'published-temple',
            'address' => 'Москва',
            'latitude' => 55.7558000,
            'longitude' => 37.6176000,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Черновик',
            'slug' => 'draft-temple',
            'address' => 'Москва',
            'latitude' => 55.7500000,
            'longitude' => 37.6100000,
            'is_published' => false,
        ]);

        $this->getJson('/api/v1/objects?type=temple')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-temple')
            ->assertJsonPath('data.0.type.slug', 'temple');
    }

    public function test_object_can_be_opened_by_slug(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Монастырь',
            'slug' => 'monastery',
            'sort_order' => 20,
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый монастырь',
            'slug' => 'test-monastery',
            'short_description' => 'Краткое описание',
            'description' => 'Полное описание объекта',
            'address' => 'Московская область',
            'latitude' => 55.5000000,
            'longitude' => 38.0000000,
            'is_published' => true,
        ]);

        $this->getJson('/api/v1/objects/test-monastery')
            ->assertOk()
            ->assertJsonPath('data.slug', 'test-monastery')
            ->assertJsonPath('data.description', 'Полное описание объекта');
    }
}
