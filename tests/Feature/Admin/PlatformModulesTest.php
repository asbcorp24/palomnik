<?php

namespace Tests\Feature\Admin;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformModulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Администратор',
            'email' => 'modules-admin@example.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_module_pages_are_protected(): void
    {
        $this->get('/admin/modules/routes')->assertRedirect('/admin/login');
        $this->get('/admin/moderation/reviews')->assertRedirect('/admin/login');
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function test_admin_can_create_route_with_objects(): void
    {
        $admin = $this->admin();
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'sort_order' => 10,
        ]);
        $object = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм',
            'slug' => 'module-test-temple',
            'address' => 'Москва',
            'latitude' => 55.75,
            'longitude' => 37.61,
            'is_published' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/modules/routes', [
            'title' => 'Тестовый маршрут',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'duration_minutes' => 240,
            'base_price' => 1500,
            'short_description' => 'Описание',
            'is_group' => 1,
            'is_published' => 1,
            'object_ids' => [$object->id],
        ]);

        $route = PilgrimageRoute::query()->firstOrFail();
        $response->assertRedirect('/admin/modules/routes/'.$route->id.'/edit');
        $this->assertNotEmpty($route->slug);
        $this->assertTrue($route->is_published);
        $this->assertDatabaseHas('pilgrimage_route_object', [
            'pilgrimage_route_id' => $route->id,
            'pilgrimage_object_id' => $object->id,
        ]);
    }

    public function test_admin_can_open_all_module_sections(): void
    {
        $admin = $this->admin();

        foreach ([
            '/admin/modules/routes',
            '/admin/modules/trips',
            '/admin/modules/achievements',
            '/admin/moderation/bookings',
            '/admin/moderation/visits',
            '/admin/moderation/reviews',
            '/admin/moderation/posts',
            '/admin/moderation/media',
            '/admin/users',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
