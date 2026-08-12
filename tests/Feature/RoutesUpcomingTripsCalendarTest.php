<?php

namespace Tests\Feature;

use App\Models\PilgrimageRoute;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoutesUpcomingTripsCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_routes_page_shows_only_upcoming_trips_for_published_routes_in_date_order(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00');

        $route = $this->route('Опубликованный маршрут', 'published-route', true);
        $hiddenRoute = $this->route('Скрытый маршрут', 'hidden-route', false);

        $nearTrip = $this->trip($route, 'Ближайшая поездка', now()->addDay(), 'open');
        $laterTrip = $this->trip($route, 'Следующая поездка', now()->addDays(4), 'planned');
        $this->trip($route, 'Прошедшая поездка', now()->subDay(), 'open');
        $this->trip($route, 'Отменённая поездка', now()->addDays(2), 'cancelled');
        $this->trip($hiddenRoute, 'Поездка скрытого маршрута', now()->addHours(3), 'open');

        $response = $this->get(route('routes.index'));

        $response
            ->assertOk()
            ->assertSee('Календарь ближайших поездок')
            ->assertSeeInOrder([$nearTrip->title, $laterTrip->title])
            ->assertDontSee('Прошедшая поездка')
            ->assertDontSee('Отменённая поездка')
            ->assertDontSee('Поездка скрытого маршрута')
            ->assertSee('Открыта запись')
            ->assertSee('Запланирована');
    }

    private function route(string $title, string $slug, bool $published): PilgrimageRoute
    {
        return PilgrimageRoute::query()->create([
            'title' => $title,
            'slug' => $slug,
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'duration_minutes' => 360,
            'short_description' => 'Тестовый маршрут',
            'description' => 'Описание тестового маршрута',
            'program' => 'Программа',
            'base_price' => 1000,
            'is_group' => true,
            'is_published' => $published,
            'published_at' => $published ? now()->subDay() : null,
        ]);
    }

    private function trip(PilgrimageRoute $route, string $title, Carbon $startsAt, string $status): Trip
    {
        return Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(6),
            'meeting_point' => 'Москва, место сбора',
            'capacity' => 20,
            'booked_count' => 3,
            'price' => 1500,
            'status' => $status,
            'notes' => null,
        ]);
    }
}
