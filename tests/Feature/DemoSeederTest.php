<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\JointPilgrimage;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_complete_public_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(9, PilgrimageObject::query()->published()->count());
        $this->assertGreaterThanOrEqual(4, PilgrimageRoute::query()->published()->count());
        $this->assertGreaterThanOrEqual(4, CalendarEvent::query()->published()->count());
        $this->assertGreaterThanOrEqual(2, JointPilgrimage::query()->published()->count());
        $this->assertDatabaseHas('users', ['email' => 'demo@palomnik.local']);
        $this->assertDatabaseHas('bookings', ['ticket_code' => 'MP-DEMO-001']);

        $this->get('/')->assertOk();
        $this->get('/map')->assertOk()->assertSee('MapLibre');
        $this->get('/objects')->assertOk();
        $this->get('/routes')->assertOk();
        $this->get('/calendar')->assertOk();
        $this->get('/community')->assertOk();
        $this->get('/community/together')->assertOk();
    }

    public function test_demo_user_has_personal_cabinet_content(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::query()->where('email', 'demo@palomnik.local')->firstOrFail();

        $this->assertTrue(Booking::query()->where('user_id', $user->id)->exists());
        $this->assertTrue($user->favoriteLists()->where('is_default', true)->exists());
        $this->assertTrue($user->routePlans()->exists());
        $this->assertTrue($user->visits()->exists());
        $this->assertTrue($user->reviews()->exists());

        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->get('/profile/favorites')->assertOk();
        $this->actingAs($user)->get('/profile/bookings')->assertOk();
        $this->actingAs($user)->get('/my-routes')->assertOk();
    }
}
