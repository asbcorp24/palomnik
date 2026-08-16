<?php

namespace Tests\Feature\Api;

use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_register_and_receive_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Паломник',
            'email' => 'mobile@example.test',
            'phone' => '+79990000001',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'consent' => true,
            'device_name' => 'Flutter test',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'preferences']]);

        $this->assertDatabaseHas('favorite_lists', [
            'user_id' => $response->json('user.id'),
            'is_default' => true,
        ]);
    }

    public function test_mobile_home_is_public(): void
    {
        $this->getJson('/api/v1/mobile/home')
            ->assertOk()
            ->assertJsonStructure(['objects', 'routes', 'events']);
    }

    public function test_authenticated_mobile_user_can_create_booking_with_qr_token(): void
    {
        $user = User::query()->create([
            'name' => 'Мобильный пользователь',
            'email' => 'booking-mobile@example.test',
            'password' => bcrypt('Password123'),
            'role' => User::ROLE_PILGRIM,
            'is_active' => true,
            'preferences' => [],
        ]);

        $route = PilgrimageRoute::query()->create([
            'title' => 'Тестовый маршрут',
            'slug' => 'mobile-test-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'base_price' => 1000,
            'is_group' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $trip = Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'title' => 'Тестовая поездка',
            'starts_at' => now()->addWeek(),
            'meeting_point' => 'Москва',
            'capacity' => 20,
            'booked_count' => 0,
            'price' => 1200,
            'status' => 'open',
        ]);

        $token = $user->createToken('Flutter test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/mobile/trips/'.$trip->id.'/bookings', [
            'participants_count' => 2,
            'contact_name' => $user->name,
            'email' => $user->email,
            'phone' => '+79990000002',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['message', 'booking_id', 'ticket_code']);

        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('booking_id'),
            'user_id' => $user->id,
            'participants_count' => 2,
            'total_amount' => 2400,
        ]);

        $booking = $user->bookings()->firstOrFail();
        $this->assertNotEmpty($booking->ticket_token);
        $this->assertSame(2, $trip->fresh()->booked_count);
    }

    public function test_mobile_user_can_manage_favorite_lists_and_activity(): void
    {
        $user = User::query()->create([
            'name' => 'Паломник',
            'email' => 'parity@example.test',
            'password' => bcrypt('Password123'),
            'role' => User::ROLE_PILGRIM,
            'is_active' => true,
            'preferences' => [],
        ]);
        $type = ObjectType::query()->create(['name' => 'Храм', 'slug' => 'temple', 'sort_order' => 1]);
        $object = PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Храм для мобильного приложения',
            'slug' => 'mobile-parity-temple',
            'is_published' => true,
        ]);
        $token = $user->createToken('Flutter parity')->plainTextToken;

        $listId = $this->withToken($token)
            ->postJson('/api/v1/mobile/favorite-lists', ['name' => 'На выходные'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/mobile/favorite-lists/'.$listId.'/objects/'.$object->id)
            ->assertOk();
        $this->withToken($token)
            ->getJson('/api/v1/mobile/favorites')
            ->assertOk()
            ->assertJsonFragment(['name' => 'На выходные'])
            ->assertJsonFragment(['slug' => 'mobile-parity-temple']);

        Visit::query()->create([
            'user_id' => $user->id,
            'pilgrimage_object_id' => $object->id,
            'visited_at' => now(),
            'verification_method' => 'manual',
            'status' => 'pending',
        ]);
        $review = Review::query()->create([
            'user_id' => $user->id,
            'pilgrimage_object_id' => $object->id,
            'rating' => 5,
            'body' => 'Очень красивый и спокойный храм.',
            'status' => 'pending',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/activity')
            ->assertOk()
            ->assertJsonPath('visits.0.object.slug', 'mobile-parity-temple')
            ->assertJsonPath('reviews.0.id', $review->id);
        $this->withToken($token)
            ->deleteJson('/api/v1/mobile/reviews/'.$review->id)
            ->assertOk();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
