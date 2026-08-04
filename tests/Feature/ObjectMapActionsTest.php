<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectMapActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_object_card_has_route_modes_without_duplicate_general_map_button(): void
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'church',
            'marker_color' => '#8B6F47',
            'icon' => 'building',
            'sort_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $object = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм',
            'slug' => 'test-church-map-actions',
            'address' => 'Москва, тестовый адрес',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('objects.show', $object))
            ->assertOk()
            ->assertSee('Построить маршрут в Яндекс Картах')
            ->assertSee('Пешком')
            ->assertSee('Общественный транспорт')
            ->assertSee('На автомобиле')
            ->assertDontSee('Открыть на общей карте');
    }
}
