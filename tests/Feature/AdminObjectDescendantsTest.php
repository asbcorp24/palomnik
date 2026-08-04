<?php

namespace Tests\Feature;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminObjectDescendantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_shows_nested_descendants_with_links_to_their_cards(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $monasteryType = $this->objectType('Монастырь', 'monastery');
        $churchType = $this->objectType('Храм', 'church');
        $chapelType = $this->objectType('Часовня', 'chapel');

        $monastery = $this->object($monasteryType, 'Тестовый монастырь', 'test-monastery');
        $church = $this->object($churchType, 'Храм при монастыре', 'monastery-church', $monastery);
        $chapel = $this->object($chapelType, 'Часовня при храме', 'church-chapel', $church, false);

        $response = $this->actingAs($admin)
            ->get(route('admin.objects.edit', $monastery));

        $response
            ->assertOk()
            ->assertSee('Дочерние объекты')
            ->assertSee('Храм при монастыре')
            ->assertSee('Часовня при храме')
            ->assertSee(route('admin.objects.edit', $church), false)
            ->assertSee(route('admin.objects.edit', $chapel), false)
            ->assertSee('Опубликован')
            ->assertSee('Черновик');
    }

    public function test_edit_form_does_not_show_descendant_block_without_children(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $churchType = $this->objectType('Храм', 'church');
        $church = $this->object($churchType, 'Самостоятельный храм', 'single-church');

        $this->actingAs($admin)
            ->get(route('admin.objects.edit', $church))
            ->assertOk()
            ->assertDontSee('Дочерние объекты');
    }

    private function objectType(string $name, string $slug): ObjectType
    {
        return ObjectType::query()->create([
            'name' => $name,
            'slug' => $slug,
            'marker_color' => '#8B6F47',
            'icon' => 'building',
            'sort_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    private function object(
        ObjectType $type,
        string $name,
        string $slug,
        ?PilgrimageObject $parent = null,
        bool $published = true
    ): PilgrimageObject {
        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'parent_object_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Москва, тестовый адрес',
            'latitude' => 55.7558,
            'longitude' => 37.6176,
            'verification_status' => PilgrimageObject::VERIFICATION_UNVERIFIED,
            'is_published' => $published,
            'published_at' => $published ? now()->subMinute() : null,
        ]);
    }
}
