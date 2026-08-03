<?php

namespace Tests\Feature;

use App\Models\PilgrimageRoute;
use App\Models\User;
use App\Models\UserMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PilgrimagePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_keep_resized_photo_private_and_request_publication_for_route(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $route = $this->publishedRoute();

        $this->actingAs($user)
            ->post('/profile/photos', [
                'file' => UploadedFile::fake()->image('pilgrimage.jpg', 3000, 2000),
                'title' => 'Дорога к святыне',
            ])
            ->assertRedirect();

        $photo = UserMedia::query()->firstOrFail();
        $this->assertSame('private', $photo->status);
        $this->assertFalse($photo->publication_requested);
        Storage::disk('public')->assertExists($photo->path);

        [$width, $height] = getimagesize(Storage::disk('public')->path($photo->path));
        $this->assertSame(1620, $width);
        $this->assertSame(1080, $height);

        $this->actingAs($user)
            ->post('/profile/photos/'.$photo->id.'/publication', [
                'pilgrimage_route_id' => $route->id,
            ])
            ->assertRedirect();

        $photo->refresh();
        $this->assertSame('pending', $photo->status);
        $this->assertTrue($photo->publication_requested);
        $this->assertSame($route->id, $photo->pilgrimage_route_id);
    }

    public function test_publication_requires_route_and_moderator_can_publish_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $route = $this->publishedRoute();
        $photo = UserMedia::query()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'path' => UploadedFile::fake()->image('photo.jpg')->store('pilgrimage-photos', 'public'),
            'title' => 'Паломнический день',
            'status' => 'private',
            'publication_requested' => false,
        ]);

        $this->actingAs($user)
            ->post('/profile/photos/'.$photo->id.'/publication', [])
            ->assertSessionHasErrors('pilgrimage_route_id');

        $this->actingAs($user)
            ->post('/profile/photos/'.$photo->id.'/publication', [
                'pilgrimage_route_id' => $route->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put('/admin/moderation/media/'.$photo->id, [
                'status' => 'published',
                'pilgrimage_route_id' => $route->id,
                'notes' => 'Проверено.',
            ])
            ->assertRedirect();

        $photo->refresh();
        $this->assertSame('published', $photo->status);
        $this->assertNotNull($photo->published_at);
        $this->assertSame('Проверено.', $photo->moderation_notes);

        $this->get('/community/photos')
            ->assertOk()
            ->assertSee('Паломнический день');

        $this->get('/routes/'.$route->slug)
            ->assertOk()
            ->assertSee('Паломнический день');
    }

    public function test_mobile_photo_upload_can_be_private_then_sent_to_moderation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $route = $this->publishedRoute();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/mobile/media', [
            'file' => UploadedFile::fake()->image('mobile.jpg', 2500, 1400),
            'title' => 'Фото из приложения',
            'pilgrimage_route_id' => $route->id,
            'request_publication' => false,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'private')
            ->assertJsonPath('data.publication_requested', false);

        $photoId = $response->json('data.id');

        $this->postJson('/api/v1/mobile/media/'.$photoId.'/publication', [
            'pilgrimage_route_id' => $route->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.publication_requested', true)
            ->assertJsonPath('data.route.id', $route->id);
    }

    public function test_mobile_multipart_upload_accepts_string_boolean_values_from_existing_apps(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $route = $this->publishedRoute();
        Sanctum::actingAs($user);

        $this->post('/api/v1/mobile/media', [
            'file' => UploadedFile::fake()->image('private-mobile.jpg', 1200, 800),
            'title' => 'Личное фото',
            'request_publication' => 'false',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'private')
            ->assertJsonPath('data.publication_requested', false);

        $this->post('/api/v1/mobile/media', [
            'file' => UploadedFile::fake()->image('public-mobile.jpg', 1200, 800),
            'title' => 'Фото на модерацию',
            'pilgrimage_route_id' => $route->id,
            'request_publication' => 'true',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.publication_requested', true)
            ->assertJsonPath('data.route.id', $route->id);
    }

    private function publishedRoute(): PilgrimageRoute
    {
        return PilgrimageRoute::query()->create([
            'title' => 'Тестовый паломнический маршрут',
            'slug' => 'test-pilgrimage-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'short_description' => 'Маршрут для проверки фотографий.',
            'is_group' => false,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
