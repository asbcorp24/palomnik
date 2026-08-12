<?php

namespace Tests\Feature\Site;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapLibrePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_map_uses_maplibre_and_valhalla_endpoint(): void
    {
        $this->get('/map')
            ->assertOk()
            ->assertSee('MapLibre', false)
            ->assertSee('/api/v1/map/style.json', false)
            ->assertSee('/api/v1/map/route', false)
            ->assertDontSee('api-maps.yandex.ru', false)
            ->assertDontSee('ymaps.ready', false);
    }

    public function test_selected_route_enables_route_only_map_mode(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'marker_color' => '#26443b',
            'icon' => 'bi-building',
            'sort_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $first = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Первая точка маршрута',
            'slug' => 'route-point-one',
            'address' => 'Москва, точка 1',
            'latitude' => 55.751,
            'longitude' => 37.611,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $second = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Вторая точка маршрута',
            'slug' => 'route-point-two',
            'address' => 'Москва, точка 2',
            'latitude' => 55.761,
            'longitude' => 37.621,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Посторонний храм',
            'slug' => 'outside-route-temple',
            'address' => 'Москва, вне маршрута',
            'latitude' => 55.756,
            'longitude' => 37.616,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $route = PilgrimageRoute::query()->create([
            'title' => 'Тестовый маршрут',
            'slug' => 'test-route-only',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'duration_minutes' => 180,
            'is_group' => false,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $route->objects()->attach([
            $first->id => ['sort_order' => 1, 'stay_minutes' => 20],
            $second->id => ['sort_order' => 2, 'stay_minutes' => 20],
        ]);

        $this->get('/map?route='.$route->slug)
            ->assertOk()
            ->assertSee('Показаны только точки выбранного маршрута')
            ->assertSee('Первая точка маршрута')
            ->assertSee('Вторая точка маршрута')
            ->assertSee('"route_only":1', false)
            ->assertDontSee('Точки интереса рядом')
            ->assertDontSee('Посторонний храм');
    }

    public function test_route_only_viewport_does_not_return_general_objects_or_points(): void
    {
        $query = '?min_lat=55.0&max_lat=56.0&min_lng=37.0&max_lng=38.0&zoom=14&route_only=1';

        $this->getJson('/api/v1/map/objects'.$query)
            ->assertOk()
            ->assertJsonCount(0, 'features')
            ->assertJsonPath('meta.route_only', true)
            ->assertJsonPath('meta.returned', 0);

        $this->getJson('/api/v1/map/points-of-interest'.$query)
            ->assertOk()
            ->assertJsonCount(0, 'features')
            ->assertJsonPath('meta.route_only', true)
            ->assertJsonPath('meta.returned', 0);
    }
}
