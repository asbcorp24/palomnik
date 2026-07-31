<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuzzyObjectSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_catalog_search_ignores_case_and_accepts_typographical_errors(): void
    {
        $object = $this->publishedObject(
            'Храм Святителя Николая',
            'hram-svyatitelya-nikolaya',
            'Москва, Никольская улица'
        );

        $this->publishedObject(
            'Сергиевский монастырь',
            'sergievskiy-monastyr',
            'Московская область'
        );

        foreach (['ХРАМ НИКОЛАЯ', 'хрм николая', 'xрам николая', '[hfv ybrjkfz'] as $query) {
            $this->get(route('objects.index', ['q' => $query]))
                ->assertOk()
                ->assertSee($object->name)
                ->assertDontSee('Сергиевский монастырь');
        }
    }

    public function test_search_finds_object_by_sanctity_with_different_case_and_close_spelling(): void
    {
        $object = $this->publishedObject(
            'Храм Покрова Пресвятой Богородицы',
            'hram-pokrova',
            'Москва, Покровская улица'
        );

        $sanctity = Sanctity::query()->create([
            'name' => 'Икона Матроны Московской',
            'slug' => 'ikona-matrony-moskovskoy',
            'type' => 'Икона',
        ]);

        $object->sanctities()->attach($sanctity->id);

        $this->get(route('objects.index', ['q' => 'ИКОНА МАТРОНА']))
            ->assertOk()
            ->assertSee($object->name);
    }

    private function publishedObject(string $name, string $slug, string $address): PilgrimageObject
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
            'address' => $address,
            'latitude' => 55.7558000,
            'longitude' => 37.6176000,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
