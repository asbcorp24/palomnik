<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PointOfInterest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointOfInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_returns_published_points_linked_to_published_objects(): void
    {
        $object = $this->publishedObject();

        PointOfInterest::query()->create([
            'pilgrimage_object_id' => $object->id,
            'category' => 'parking',
            'name' => 'Парковка у храма',
            'address' => 'Москва, рядом с главным входом',
            'latitude' => 55.7559000,
            'longitude' => 37.6177000,
            'is_published' => true,
        ]);

        PointOfInterest::query()->create([
            'pilgrimage_object_id' => $object->id,
            'category' => 'cafe',
            'name' => 'Скрытое кафе',
            'address' => 'Москва',
            'latitude' => 55.7560000,
            'longitude' => 37.6178000,
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/v1/points-of-interest?category=parking')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'parking')
            ->assertJsonPath('data.0.category_label', 'Стоянка')
            ->assertJsonPath('data.0.base_object.id', $object->id);

        $this->assertSame('Парковка у храма', $response->json('data.0.name'));
    }

    public function test_unpublished_base_object_hides_its_points(): void
    {
        $object = $this->publishedObject();
        $object->update(['is_published' => false, 'published_at' => null]);

        PointOfInterest::query()->create([
            'pilgrimage_object_id' => $object->id,
            'category' => 'hotel',
            'name' => 'Гостиница',
            'address' => 'Москва',
            'latitude' => 55.7559000,
            'longitude' => 37.6177000,
            'is_published' => true,
        ]);

        $this->getJson('/api/v1/points-of-interest')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_object_show_api_contains_linked_points(): void
    {
        $object = $this->publishedObject();

        PointOfInterest::query()->create([
            'pilgrimage_object_id' => $object->id,
            'category' => 'cafe',
            'name' => 'Трапезная',
            'address' => 'Москва',
            'latitude' => 55.7559000,
            'longitude' => 37.6177000,
            'is_published' => true,
        ]);

        $this->getJson('/api/v1/objects/'.$object->slug)
            ->assertOk()
            ->assertJsonPath('data.points_of_interest.0.name', 'Трапезная')
            ->assertJsonPath('data.points_of_interest.0.category', 'cafe');
    }

    private function publishedObject(): PilgrimageObject
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#b08a3e',
            'icon' => 'bi-building',
            'sort_order' => 1,
        ]);

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм',
            'slug' => 'test-temple',
            'address' => 'Москва',
            'latitude' => 55.7558000,
            'longitude' => 37.6176000,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
