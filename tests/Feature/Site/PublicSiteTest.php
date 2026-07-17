<?php

namespace Tests\Feature\Site;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Московский паломник')
            ->assertSee('Святые места становятся ближе');
    }

    public function test_catalog_shows_published_objects_only(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'sort_order' => 10,
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Опубликованный храм',
            'slug' => 'published-temple-site',
            'address' => 'Москва',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Скрытый черновик',
            'slug' => 'draft-temple-site',
            'address' => 'Москва',
            'latitude' => 55.75,
            'longitude' => 37.61,
            'is_published' => false,
        ]);

        $this->get('/objects')
            ->assertOk()
            ->assertSee('Опубликованный храм')
            ->assertDontSee('Скрытый черновик');
    }

    public function test_published_object_detail_page_is_available(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Монастырь',
            'slug' => 'monastery',
            'sort_order' => 20,
        ]);

        PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый монастырь',
            'slug' => 'test-monastery-site',
            'short_description' => 'Краткое описание объекта.',
            'description' => 'Полное описание объекта.',
            'address' => 'Московская область',
            'latitude' => 55.5,
            'longitude' => 38.0,
            'is_published' => true,
        ]);

        $this->get('/objects/test-monastery-site')
            ->assertOk()
            ->assertSee('Тестовый монастырь')
            ->assertSee('Полное описание объекта.');
    }

    public function test_map_page_is_available_without_api_key(): void
    {
        config()->set('palomnik.maps.yandex_key', null);

        $this->get('/map')
            ->assertOk()
            ->assertSee('Карта подготовлена к подключению');
    }
}
