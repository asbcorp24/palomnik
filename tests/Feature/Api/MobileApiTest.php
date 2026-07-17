<?php

namespace Tests\Feature\Api;

use App\Models\PilgrimageRoute;
use App\Models\Trip;
use App\Models\User;
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
}
