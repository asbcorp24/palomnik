<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePopularObjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_six_most_popular_objects(): void
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

        $objects = collect(range(1, 7))->map(function (int $index) use ($type) {
            return PilgrimageObject::query()->create([
                'object_type_id' => $type->id,
                'name' => 'Храм популярности '.$index,
                'slug' => 'popular-home-'.$index,
                'address' => 'Москва, тестовый адрес '.$index,
                'latitude' => 55.7558,
                'longitude' => 37.6176,
                'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
                'is_published' => true,
                'published_at' => now()->subDay(),
            ]);
        });

        foreach ($objects as $index => $object) {
            $viewCount = 7 - $index;

            foreach (range(1, $viewCount) as $viewIndex) {
                AnalyticsEvent::query()->create([
                    'event' => 'object_view',
                    'entity_type' => 'PilgrimageObject',
                    'entity_id' => $object->id,
                    'path' => 'objects/'.$object->slug,
                    'created_at' => now()->subMinutes($viewIndex),
                ]);
            }
        }

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Храм популярности 1',
                'Храм популярности 2',
                'Храм популярности 3',
                'Храм популярности 4',
                'Храм популярности 5',
                'Храм популярности 6',
            ])
            ->assertDontSee('Храм популярности 7');
    }
}
