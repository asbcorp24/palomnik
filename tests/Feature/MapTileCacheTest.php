<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapTileCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'palomnik.maps.openmaptiles_tiles' => null,
            'palomnik.maps.raster_tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'palomnik.maps.tile_mode' => 'cache',
            'palomnik.maps.tile_cache_enabled' => true,
            'palomnik.maps.tile_cache_disk' => 'local',
            'palomnik.maps.tile_cache_directory' => 'map-tiles/osm',
            'palomnik.maps.tile_cache_max_size_mb' => 1024,
            'palomnik.maps.tile_default_ttl' => 604800,
            'palomnik.maps.tile_browser_ttl' => 86400,
            'palomnik.maps.tile_max_zoom' => 19,
            'palomnik.maps.tile_user_agent' => 'MoscowPilgrimTest/1.0 (tests@example.test)',
        ]);
    }

    public function test_cache_mode_uses_same_origin_tile_endpoint(): void
    {
        $response = $this->getJson('/api/v1/map/style.json')->assertOk();

        $tileUrl = (string) $response->json('sources.osm.tiles.0');

        $this->assertStringContainsString('/api/v1/map/tiles/{z}/{x}/{y}.png', $tileUrl);
        $this->assertStringNotContainsString('tile.openstreetmap.org', $tileUrl);
    }

    public function test_direct_mode_uses_external_tile_url_and_disables_cache_endpoint(): void
    {
        config([
            'palomnik.maps.tile_mode' => 'direct',
            'palomnik.maps.tile_cache_enabled' => false,
        ]);

        $response = $this->getJson('/api/v1/map/style.json')->assertOk();

        $this->assertSame(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            $response->json('sources.osm.tiles.0')
        );

        $this->get('/api/v1/map/tiles/10/619/319.png')->assertNotFound();
    }

    public function test_requested_tile_is_downloaded_once_and_then_served_from_disk(): void
    {
        $png = "\x89PNG\r\n\x1a\n".str_repeat('tile-data-', 20);

        Http::fake([
            'https://tile.openstreetmap.org/*' => Http::response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=604800',
                'ETag' => '"osm-test-etag"',
            ]),
        ]);

        $first = $this->get('/api/v1/map/tiles/10/619/319.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Map-Tile-Cache', 'MISS');

        $this->assertSame($png, $first->getContent());
        Storage::disk('local')->assertExists('map-tiles/osm/10/619/319.png');
        Storage::disk('local')->assertExists('map-tiles/osm/10/619/319.png.json');

        $second = $this->get('/api/v1/map/tiles/10/619/319.png')
            ->assertOk()
            ->assertHeader('X-Map-Tile-Cache', 'HIT');

        $this->assertSame($png, $second->getContent());
        Http::assertSentCount(1);
    }

    public function test_new_tile_is_not_saved_when_cache_size_limit_is_reached(): void
    {
        config(['palomnik.maps.tile_cache_max_size_mb' => 1]);

        Storage::disk('local')->put('map-tiles/osm/existing.bin', str_repeat('x', 1024 * 1024));

        $png = "\x89PNG\r\n\x1a\n".str_repeat('new-tile-', 20);
        Http::fake([
            'https://tile.openstreetmap.org/*' => Http::response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=604800',
            ]),
        ]);

        $response = $this->get('/api/v1/map/tiles/10/619/319.png')
            ->assertOk()
            ->assertHeader('X-Map-Tile-Cache', 'BYPASS-LIMIT');

        $this->assertSame($png, $response->getContent());
        Storage::disk('local')->assertMissing('map-tiles/osm/10/619/319.png');
        Storage::disk('local')->assertMissing('map-tiles/osm/10/619/319.png.json');
    }

    public function test_stale_tile_is_returned_when_upstream_is_unavailable(): void
    {
        $png = "\x89PNG\r\n\x1a\n".str_repeat('stale-tile-', 20);
        $path = 'map-tiles/osm/10/619/319.png';

        Storage::disk('local')->put($path, $png);
        Storage::disk('local')->put($path.'.json', json_encode([
            'cached_at' => time() - 700000,
            'last_checked_at' => time() - 700000,
            'expires_at' => time() - 10,
            'content_type' => 'image/png',
            'upstream_etag' => '"old-etag"',
        ]));

        Http::fake([
            'https://tile.openstreetmap.org/*' => Http::response('', 503),
        ]);

        $response = $this->get('/api/v1/map/tiles/10/619/319.png')
            ->assertOk()
            ->assertHeader('X-Map-Tile-Cache', 'STALE');

        $this->assertSame($png, $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_invalid_tile_coordinates_are_rejected_without_external_request(): void
    {
        Http::fake();

        $this->get('/api/v1/map/tiles/20/0/0.png')->assertNotFound();
        $this->get('/api/v1/map/tiles/10/1024/0.png')->assertNotFound();

        Http::assertNothingSent();
    }
}
