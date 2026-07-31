<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyPlacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_page_and_api_show_only_geographically_nearby_places(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#b08a3e',
            'icon' => 'bi-building',
            'sort_order' => 1,
        ]);

        $current = $this->object($type->id, 'Главный храм', 'main-temple', 55.7500, 37.6100);
        $nearby = $this->object($type->id, 'Храм рядом', 'nearby-temple', 55.7550, 37.6150);
        $far = $this->object($type->id, 'Дальний храм', 'far-temple', 56.5000, 38.5000);

        $this->get(route('objects.show', $current))
            ->assertOk()
            ->assertSee('Места рядом')
            ->assertSee($nearby->name)
            ->assertDontSee($far->name);

        $this->getJson('/api/v1/objects/'.$current->slug)
            ->assertOk()
            ->assertJsonFragment(['name' => $nearby->name])
            ->assertJsonMissing(['name' => $far->name]);
    }

    private function object(int $typeId, string $name, string $slug, float $latitude, float $longitude): PilgrimageObject
    {
        return PilgrimageObject::query()->create([
            'object_type_id' => $typeId,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Москва',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
