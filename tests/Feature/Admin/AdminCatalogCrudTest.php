<?php

namespace Tests\Feature\Admin;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\User;
use App\Models\Vicariate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_regular_pilgrim_cannot_open_admin_panel(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_PILGRIM,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_create_vicariate(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/directories/vicariates', [
                'name' => 'Центральное викариатство',
                'slug' => 'central-vicariate',
                'description' => 'Тестовое описание',
            ])
            ->assertRedirect('/admin/directories/vicariates');

        $this->assertDatabaseHas('vicariates', [
            'name' => 'Центральное викариатство',
            'slug' => 'central-vicariate',
        ]);
    }

    public function test_admin_can_create_and_update_pilgrimage_object(): void
    {
        $admin = $this->admin();
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'sort_order' => 10,
        ]);
        $vicariate = Vicariate::query()->create([
            'name' => 'Центральное викариатство',
            'slug' => 'central',
        ]);

        $this->actingAs($admin)
            ->post('/admin/objects', [
                'object_type_id' => $type->id,
                'vicariate_id' => $vicariate->id,
                'name' => 'Тестовый храм',
                'slug' => 'test-temple',
                'address' => 'Москва, тестовый адрес',
                'latitude' => '55.7558000',
                'longitude' => '37.6176000',
                'is_published' => '1',
            ])
            ->assertRedirect();

        $object = PilgrimageObject::query()->where('slug', 'test-temple')->firstOrFail();
        $this->assertTrue($object->is_published);

        $this->actingAs($admin)
            ->put('/admin/objects/'.$object->slug, [
                'object_type_id' => $type->id,
                'vicariate_id' => $vicariate->id,
                'name' => 'Обновлённый тестовый храм',
                'slug' => 'test-temple',
                'address' => 'Москва, новый адрес',
                'latitude' => '55.7558000',
                'longitude' => '37.6176000',
                'is_published' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pilgrimage_objects', [
            'id' => $object->id,
            'name' => 'Обновлённый тестовый храм',
            'is_published' => false,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }
}
