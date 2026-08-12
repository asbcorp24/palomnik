<?php

namespace Tests\Feature\Admin;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteConstructorTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructor_saves_order_stay_time_and_point_notes(): void
    {
        $admin = $this->admin();
        [$first, $second, $third] = $this->objects();

        $response = $this->actingAs($admin)->post('/admin/modules/routes', [
            'title' => 'Маршрут-конструктор',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'is_group' => 0,
            'is_published' => 0,
            'object_ids' => [$second->id, $first->id],
            'stay_minutes' => [
                $second->id => 45,
                $first->id => 20,
            ],
            'point_notes' => [
                $second->id => 'Главная остановка',
                $first->id => 'Начало маршрута',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $route = PilgrimageRoute::query()->firstOrFail();
        $response->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');

        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $second->id,
            'sort_order' => 1,
            'stay_minutes' => 45,
            'note' => 'Главная остановка',
        ]);
        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $first->id,
            'sort_order' => 2,
            'stay_minutes' => 20,
            'note' => 'Начало маршрута',
        ]);

        $edit = $this->actingAs($admin)->get('/admin/modules/routes/'.$route->id.'/edit');
        $edit->assertOk()
            ->assertSee('Конструктор маршрута')
            ->assertSee('Остановка, мин.')
            ->assertSeeInOrder([$second->name, $first->name]);

        $update = $this->actingAs($admin)->put('/admin/modules/routes/'.$route->id, [
            'title' => $route->title,
            'slug' => $route->slug,
            'category' => $route->category,
            'difficulty' => $route->difficulty,
            'duration_days' => 1,
            'is_group' => 0,
            'is_published' => 0,
            'object_ids' => [$third->id, $first->id, $second->id],
            'stay_minutes' => [
                $third->id => 15,
                $first->id => 25,
                $second->id => 60,
            ],
            'point_notes' => [
                $third->id => 'Новая первая точка',
            ],
        ]);

        $update->assertSessionHasNoErrors();
        $update->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');
        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $third->id,
            'sort_order' => 1,
            'stay_minutes' => 15,
            'note' => 'Новая первая точка',
        ]);
        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $first->id,
            'sort_order' => 2,
            'stay_minutes' => 25,
            'note' => null,
        ]);
        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $second->id,
            'sort_order' => 3,
            'stay_minutes' => 60,
            'note' => null,
        ]);
    }

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Администратор маршрутов',
            'email' => 'route-constructor@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function objects(): array
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'route-constructor-temple',
            'sort_order' => 10,
        ]);

        return collect([
            ['name' => 'Храм Альфа', 'slug' => 'route-alpha', 'latitude' => 55.75, 'longitude' => 37.61],
            ['name' => 'Храм Бета', 'slug' => 'route-beta', 'latitude' => 55.76, 'longitude' => 37.62],
            ['name' => 'Храм Гамма', 'slug' => 'route-gamma', 'latitude' => 55.77, 'longitude' => 37.63],
        ])->map(fn (array $data) => PilgrimageObject::query()->create(array_merge($data, [
            'object_type_id' => $type->id,
            'address' => 'Москва, '.$data['name'],
            'is_published' => true,
        ])))->all();
    }
}
