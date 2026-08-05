<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectCatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_catalog_sorts_popular_objects_and_filters_objects_with_reviews(): void
    {
        [$popular, $reviewed, $ordinary] = $this->catalogObjects();

        $this->addViews($popular, 4);
        $this->addViews($reviewed, 1);
        $this->addPublishedReviews($reviewed, [5, 4]);
        $this->addPublishedReviews($popular, [3]);

        $this->get('/objects?sort=popular')
            ->assertOk()
            ->assertSee('Без сортировки')
            ->assertSee('Популярные')
            ->assertSee('С отзывами')
            ->assertSeeInOrder([
                $popular->name,
                $reviewed->name,
                $ordinary->name,
            ]);

        $this->get('/objects?sort=reviews')
            ->assertOk()
            ->assertSeeInOrder([
                $reviewed->name,
                $popular->name,
            ])
            ->assertDontSee($ordinary->name);
    }

    public function test_mobile_api_uses_the_same_popular_and_review_sorting(): void
    {
        [$popular, $reviewed, $ordinary] = $this->catalogObjects();

        $this->addViews($popular, 5);
        $this->addViews($reviewed, 2);
        $this->addPublishedReviews($reviewed, [5, 5, 4]);
        $this->addPublishedReviews($popular, [4]);

        $this->getJson('/api/v1/objects?sort=popular&per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.name', $popular->name)
            ->assertJsonPath('data.1.name', $reviewed->name)
            ->assertJsonPath('data.2.name', $ordinary->name);

        $this->getJson('/api/v1/objects?sort=reviews&per_page=100')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $reviewed->name)
            ->assertJsonPath('data.1.name', $popular->name);
    }

    private function catalogObjects(): array
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

        return [
            $this->object($type, 'Популярный храм', 'popular-temple'),
            $this->object($type, 'Храм с отзывами', 'reviewed-temple'),
            $this->object($type, 'Обычный храм', 'ordinary-temple'),
        ];
    }

    private function object(ObjectType $type, string $name, string $slug): PilgrimageObject
    {
        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Москва, тестовый адрес',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function addViews(PilgrimageObject $object, int $count): void
    {
        foreach (range(1, $count) as $index) {
            AnalyticsEvent::query()->create([
                'event' => 'object_view',
                'entity_type' => 'PilgrimageObject',
                'entity_id' => $object->id,
                'path' => 'objects/'.$object->slug,
                'created_at' => now()->subMinutes($index),
            ]);
        }
    }

    private function addPublishedReviews(PilgrimageObject $object, array $ratings): void
    {
        foreach ($ratings as $rating) {
            $user = User::factory()->create();
            Review::query()->create([
                'user_id' => $user->id,
                'pilgrimage_object_id' => $object->id,
                'rating' => $rating,
                'body' => 'Опубликованный отзыв о паломническом объекте.',
                'status' => 'published',
                'moderated_at' => now(),
            ]);
        }
    }
}
