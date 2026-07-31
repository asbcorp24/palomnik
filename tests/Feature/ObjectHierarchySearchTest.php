<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectHierarchySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_temple_query_returns_temple_and_its_parent_monastery(): void
    {
        [$monastery, $temple] = $this->hierarchy();

        $this->get(route('objects.index', ['q' => 'храм']))
            ->assertOk()
            ->assertSee($temple->name)
            ->assertSee($monastery->name)
            ->assertSee('На территории: 1 связанных объектов');

        $this->getJson('/api/v1/objects?q=храм')
            ->assertOk()
            ->assertJsonFragment(['id' => $temple->id, 'name' => $temple->name])
            ->assertJsonFragment(['id' => $monastery->id, 'name' => $monastery->name]);
    }

    public function test_temple_type_filter_includes_parent_monastery(): void
    {
        [$monastery, $temple] = $this->hierarchy();

        $this->get(route('objects.index', ['type' => 'temple']))
            ->assertOk()
            ->assertSee($temple->name)
            ->assertSee($monastery->name);

        $this->getJson('/api/v1/objects?type=temple')
            ->assertOk()
            ->assertJsonFragment(['id' => $temple->id, 'name' => $temple->name])
            ->assertJsonFragment(['id' => $monastery->id, 'name' => $monastery->name]);
    }

    public function test_parent_and_child_relationships_are_available(): void
    {
        [$monastery, $temple] = $this->hierarchy();

        $this->assertTrue($temple->parentObject->is($monastery));
        $this->assertTrue($monastery->childObjects->contains($temple));
        $this->assertTrue($monastery->publishedChildObjects->contains($temple));
    }

    /** @return array{PilgrimageObject, PilgrimageObject} */
    private function hierarchy(): array
    {
        $monasteryType = ObjectType::query()->create([
            'name' => 'Монастырь',
            'slug' => 'monastery',
            'marker_color' => '#26443b',
            'icon' => 'bi-buildings',
            'sort_order' => 1,
        ]);
        $templeType = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#b08a3e',
            'icon' => 'bi-building',
            'sort_order' => 2,
        ]);

        $monastery = PilgrimageObject::query()->create([
            'object_type_id' => $monasteryType->id,
            'name' => 'Покровский монастырь',
            'slug' => 'pokrovskiy-monastyr',
            'address' => 'Москва, монастырская территория',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        $temple = PilgrimageObject::query()->create([
            'object_type_id' => $templeType->id,
            'parent_object_id' => $monastery->id,
            'name' => 'Храм Покрова на территории монастыря',
            'slug' => 'hram-pokrova-v-monastyre',
            'address' => 'Москва, территория Покровского монастыря',
            'latitude' => 55.7559,
            'longitude' => 37.6177,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        return [$monastery->fresh(), $temple->fresh()];
    }
}
