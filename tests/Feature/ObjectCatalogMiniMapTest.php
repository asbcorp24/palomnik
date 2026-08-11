<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectCatalogMiniMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_objects_catalog_contains_compact_map_with_current_page_objects(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#8B6F47',
            'icon' => 'church',
            'sort_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $object = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Храм с мини-картой',
            'slug' => 'temple-with-mini-map',
            'address' => 'Москва, тестовый адрес',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->get('/objects')
            ->assertOk()
            ->assertSee('Объекты этой страницы')
            ->assertSee('Открыть большую карту')
            ->assertSee('id="objectCatalogMiniMap"', false)
            ->assertSee('object-catalog-mini-map.js', false)
            ->assertSee('/api/v1/map/style.json', false)
            ->assertSee($object->name)
            ->assertSee('55.7558', false)
            ->assertSee('37.6176', false);
    }

    public function test_objects_catalog_keeps_compact_map_when_catalog_is_empty(): void
    {
        $this->get('/objects')
            ->assertOk()
            ->assertSee('Объекты этой страницы')
            ->assertSee('id="objectCatalogMiniMap"', false)
            ->assertSee('<script type="application/json" id="objectCatalogMiniMapData">[]</script>', false);
    }
}
