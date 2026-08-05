<?php

namespace Tests\Feature;

use App\Models\ObjectRepresentative;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicObjectEditButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_link_to_admin_object_editor(): void
    {
        $object = $this->publishedObject();
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('objects.show', $object))
            ->assertOk()
            ->assertSee('Редактировать карточку')
            ->assertSee(route('admin.objects.edit', $object), false);
    }

    public function test_approved_object_editor_sees_link_to_service_editor_for_assigned_object(): void
    {
        $object = $this->publishedObject();
        $editor = $this->user(User::ROLE_OBJECT_EDITOR);

        ObjectRepresentative::query()->create([
            'pilgrimage_object_id' => $object->id,
            'user_id' => $editor->id,
            'role' => 'representative',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $this->actingAs($editor)
            ->get(route('objects.show', $object))
            ->assertOk()
            ->assertSee('Редактировать карточку')
            ->assertSee(route('service.objects.edit', $object), false)
            ->assertDontSee(route('admin.objects.edit', $object), false);
    }

    public function test_user_without_actual_edit_access_does_not_see_button(): void
    {
        $object = $this->publishedObject();
        $editor = $this->user(User::ROLE_OBJECT_EDITOR);

        ObjectRepresentative::query()->create([
            'pilgrimage_object_id' => $object->id,
            'user_id' => $editor->id,
            'role' => 'representative',
            'status' => 'pending',
        ]);

        $this->actingAs($editor)
            ->get(route('objects.show', $object))
            ->assertOk()
            ->assertDontSee('Редактировать карточку')
            ->assertDontSee(route('service.objects.edit', $object), false);
    }

    public function test_guest_does_not_see_edit_button(): void
    {
        $object = $this->publishedObject();

        $this->get(route('objects.show', $object))
            ->assertOk()
            ->assertDontSee('Редактировать карточку');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function publishedObject(): PilgrimageObject
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

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм',
            'slug' => 'test-editable-temple',
            'address' => 'Москва, тестовый адрес',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }
}
