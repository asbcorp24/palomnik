<?php

namespace Tests\Feature\Site;

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
}
