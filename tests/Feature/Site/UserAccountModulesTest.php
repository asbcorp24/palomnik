<?php

namespace Tests\Feature\Site;

use App\Models\FavoriteList;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserRoutePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAccountModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_profile_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_register_and_get_default_favorite_list(): void
    {
        $response = $this->post('/register', [
            'name' => 'Алексей Паломник',
            'email' => 'pilgrim@example.test',
            'phone' => '',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'consent' => '1',
        ]);

        $response->assertRedirect('/profile');

        $user = User::query()->where('email', 'pilgrim@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_PILGRIM, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertDatabaseHas('favorite_lists', [
            'user_id' => $user->id,
            'name' => 'Избранное',
            'is_default' => true,
        ]);
    }

    public function test_registered_user_can_use_favorites_reviews_and_visits(): void
    {
        $user = $this->user();
        $object = $this->object('Храм для пользователя', 'user-temple', 55.751244, 37.618423);
        $list = FavoriteList::query()->create([
            'user_id' => $user->id,
            'name' => 'Избранное',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->post('/favorites/objects/'.$object->id)
            ->assertRedirect();

        $this->assertDatabaseHas('favorite_list_object', [
            'favorite_list_id' => $list->id,
            'pilgrimage_object_id' => $object->id,
        ]);

        $this->actingAs($user)
            ->post('/objects/'.$object->id.'/reviews', [
                'rating' => 5,
                'body' => 'Очень важное и красивое место для паломнического посещения.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'pilgrimage_object_id' => $object->id,
            'rating' => 5,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post('/objects/'.$object->id.'/visits', [
                'latitude' => 55.751244,
                'longitude' => 37.618423,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('visits', [
            'user_id' => $user->id,
            'pilgrimage_object_id' => $object->id,
            'status' => 'verified',
            'verification_method' => 'geolocation',
        ]);
    }

    public function test_user_can_create_personal_route(): void
    {
        $user = $this->user();
        $first = $this->object('Первый храм', 'first-route-temple', 55.75, 37.61);
        $second = $this->object('Второй храм', 'second-route-temple', 55.76, 37.62);

        $response = $this->actingAs($user)->post('/my-routes', [
            'name' => 'Мой московский путь',
            'transport_mode' => 'walk',
            'object_ids' => [$first->id, $second->id],
            'notes' => 'Тестовый персональный маршрут.',
        ]);

        $plan = UserRoutePlan::query()->firstOrFail();
        $response->assertRedirect('/my-routes/'.$plan->id);
        $this->assertDatabaseHas('user_route_plans', [
            'id' => $plan->id,
            'user_id' => $user->id,
            'transport_mode' => 'walk',
        ]);
        $this->assertDatabaseCount('user_route_plan_object', 2);
    }

    public function test_user_can_book_open_trip(): void
    {
        $user = $this->user();
        $route = PilgrimageRoute::query()->create([
            'title' => 'Групповой маршрут',
            'slug' => 'group-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'base_price' => 1200,
            'is_group' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        $trip = Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'starts_at' => now()->addWeek(),
            'meeting_point' => 'Москва',
            'capacity' => 20,
            'booked_count' => 0,
            'price' => 1500,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->post('/trips/'.$trip->id.'/bookings', [
            'participants_count' => 2,
            'contact_name' => $user->name,
            'email' => $user->email,
            'phone' => '+79990000000',
            'consent' => '1',
        ]);

        $response->assertRedirect('/profile/bookings');
        $this->assertDatabaseHas('bookings', [
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'participants_count' => 2,
            'total_amount' => '3000.00',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'booked_count' => 2,
        ]);
    }

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Пользователь',
            'email' => Str::lower(Str::random(12)).'@example.test',
            'phone' => null,
            'password' => bcrypt('Password123'),
            'role' => User::ROLE_PILGRIM,
            'is_active' => true,
            'preferences' => [],
        ]);
    }

    private function object(string $name, string $slug, float $latitude, float $longitude): PilgrimageObject
    {
        $type = ObjectType::query()->firstOrCreate(
            ['slug' => 'temple'],
            ['name' => 'Храм', 'sort_order' => 10]
        );

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => $name,
            'slug' => $slug,
            'address' => 'Москва',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
