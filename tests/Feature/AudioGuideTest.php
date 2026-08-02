<?php

namespace Tests\Feature;

use App\Models\AudioGuide;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_audio_guide_to_object_and_public_page_plays_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $object = $this->publishedObject();

        $this->actingAs($admin)
            ->put(route('admin.objects.audio-guide.update', $object), [
                'title' => 'История храма',
                'transcript' => 'Текстовая расшифровка аудиогида.',
                'audio_file' => UploadedFile::fake()->create('temple-guide.mp3', 256, 'audio/mpeg'),
            ])
            ->assertRedirect();

        $guide = AudioGuide::query()->firstOrFail();
        $this->assertSame(PilgrimageObject::class, $guide->guideable_type);
        $this->assertSame($object->id, $guide->guideable_id);
        $this->assertSame('История храма', $guide->title);
        Storage::disk('public')->assertExists($guide->path);

        $this->get(route('objects.show', $object))
            ->assertOk()
            ->assertSee('История храма')
            ->assertSee($guide->url, false);
    }

    public function test_route_manager_can_add_replace_and_remove_route_audio_guide(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create(['role' => User::ROLE_SERVICE_MANAGER]);
        $route = $this->publishedRoute();

        $this->actingAs($manager)
            ->put(route('admin.routes.audio-guide.update', $route), [
                'title' => 'Аудиогид маршрута',
                'transcript' => 'Начните путь от первой точки.',
                'audio_file' => UploadedFile::fake()->create('route-guide.mp3', 256, 'audio/mpeg'),
            ])
            ->assertRedirect();

        $guide = $route->fresh()->audioGuide;
        $this->assertNotNull($guide);
        $oldPath = $guide->path;
        Storage::disk('public')->assertExists($oldPath);

        $this->get(route('routes.show', $route))
            ->assertOk()
            ->assertSee('Аудиогид маршрута')
            ->assertSee('Показать текстовую расшифровку')
            ->assertSee($guide->url, false);

        $this->actingAs($manager)
            ->put(route('admin.routes.audio-guide.update', $route), [
                'title' => 'Новая версия аудиогида',
                'transcript' => 'Обновлённый текст.',
                'audio_file' => UploadedFile::fake()->create('route-guide-new.ogg', 128, 'audio/ogg'),
            ])
            ->assertRedirect();

        $guide = $route->fresh()->audioGuide;
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($guide->path);

        $this->actingAs($manager)
            ->delete(route('admin.routes.audio-guide.destroy', $route))
            ->assertRedirect();

        $this->assertDatabaseCount('audio_guides', 0);
        Storage::disk('public')->assertMissing($guide->path);
    }

    private function publishedObject(): PilgrimageObject
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple',
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм с аудиогидом',
            'slug' => 'test-audio-temple',
            'address' => 'Москва, тестовая улица, 1',
            'latitude' => 55.75,
            'longitude' => 37.61,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }

    private function publishedRoute(): PilgrimageRoute
    {
        return PilgrimageRoute::query()->create([
            'title' => 'Тестовый маршрут с аудиогидом',
            'slug' => 'test-audio-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'short_description' => 'Маршрут для проверки аудиогида.',
            'is_group' => false,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
