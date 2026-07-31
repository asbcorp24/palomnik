<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeObjectSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_case_insensitive_for_cyrillic_text(): void
    {
        $this->publishedObject('храм святителя Николая', 'nikolay-temple');

        $this->get('/objects?q=ХРАМ')
            ->assertOk()
            ->assertSee('храм святителя Николая');
    }

    public function test_search_finds_object_with_a_small_typo(): void
    {
        $this->publishedObject('Храм святителя Николая', 'nikolay-temple');

        $this->get('/objects?q=Никлая')
            ->assertOk()
            ->assertSee('Храм святителя Николая');
    }

    public function test_search_treats_yo_and_e_as_the_same_letter(): void
    {
        $this->publishedObject('Храм Фёдора Стратилата', 'fedor-temple');

        $this->get('/objects?q=ФЕДОРА')
            ->assertOk()
            ->assertSee('Храм Фёдора Стратилата');
    }

    public function test_search_recognizes_text_typed_in_the_wrong_keyboard_layout(): void
    {
        $this->publishedObject('Храм Архангела Михаила', 'archangel-temple');

        $this->get('/objects?q=%5Bhfv')
            ->assertOk()
            ->assertSee('Храм Архангела Михаила');
    }

    private function publishedObject(string $name, string $slug): PilgrimageObject
    {
        $type = ObjectType::query()->firstOrCreate(
            ['slug' => 'temple'],
            [
                'name' => 'Храм',
                'marker_color' => '#b08a3e',
                'icon' => 'bi-building',
                'sort_order' => 1,
            ]
        );

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Москва',
            'latitude' => 55.7558000,
            'longitude' => 37.6176000,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
