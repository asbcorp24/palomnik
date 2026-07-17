<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapApiTest extends TestCase
{
    public function test_map_config_exposes_runtime_capabilities(): void
    {
        config()->set('palomnik.maps.openmaptiles_tiles', 'https://tiles.example.test/{z}/{x}/{y}.pbf');
        config()->set('palomnik.maps.offline_enabled', true);
        config()->set('palomnik.maps.offline_tile_limit', 75000);
        config()->set('palomnik.maps.valhalla_url', 'https://route.example.test');

        $this->getJson('/api/v1/map/config')
            ->assertOk()
            ->assertJsonPath('data.provider', 'openmaptiles')
            ->assertJsonPath('data.offline_enabled', true)
            ->assertJsonPath('data.offline_tile_limit', 75000)
            ->assertJsonPath('data.routing_enabled', true);
    }

    public function test_style_endpoint_returns_openmaptiles_vector_style(): void
    {
        config()->set('palomnik.maps.openmaptiles_tiles', 'https://tiles.example.test/{z}/{x}/{y}.pbf');
        config()->set('palomnik.maps.glyphs_url', 'https://tiles.example.test/fonts/{fontstack}/{range}.pbf');

        $this->getJson('/api/v1/map/style.json')
            ->assertOk()
            ->assertJsonPath('version', 8)
            ->assertJsonPath('sources.openmaptiles.type', 'vector')
            ->assertJsonPath('sources.openmaptiles.tiles.0', 'https://tiles.example.test/{z}/{x}/{y}.pbf')
            ->assertJsonPath('glyphs', 'https://tiles.example.test/fonts/{fontstack}/{range}.pbf');
    }

    public function test_route_endpoint_proxies_valhalla_and_normalizes_response(): void
    {
        config()->set('palomnik.maps.valhalla_url', 'https://route.example.test');

        Http::fake([
            'https://route.example.test/route' => Http::response([
                'routes' => [[
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [37.618423, 55.751244],
                            [37.701000, 55.780000],
                        ],
                    ],
                    'distance' => 8200.5,
                    'duration' => 1420.0,
                    'weight' => 1420.0,
                    'legs' => [],
                ]],
                'waypoints' => [],
            ]),
        ]);

        $this->postJson('/api/v1/map/route', [
            'mode' => 'pedestrian',
            'locations' => [
                ['latitude' => 55.751244, 'longitude' => 37.618423],
                ['latitude' => 55.780000, 'longitude' => 37.701000],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.geometry.type', 'LineString')
            ->assertJsonPath('data.distance_meters', 8200.5)
            ->assertJsonPath('data.duration_seconds', 1420)
            ->assertJsonPath('data.mode', 'pedestrian')
            ->assertJsonPath('data.optimized', false);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://route.example.test/route'
                && $request['costing'] === 'pedestrian'
                && $request['format'] === 'osrm'
                && $request['shape_format'] === 'geojson';
        });
    }

    public function test_route_endpoint_rejects_invalid_mode(): void
    {
        $this->postJson('/api/v1/map/route', [
            'mode' => 'helicopter',
            'locations' => [
                ['latitude' => 55.75, 'longitude' => 37.61],
                ['latitude' => 55.76, 'longitude' => 37.62],
            ],
        ])->assertUnprocessable();
    }
}
