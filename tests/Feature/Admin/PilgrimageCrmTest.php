<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingCrmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilgrimageCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manual_request_with_named_participants(): void
    {
        $admin = $this->admin();
        $trip = $this->trip();

        $response = $this->actingAs($admin)->post('/admin/crm', [
            'trip_id' => $trip->id,
            'contact_name' => 'Иван Паломник',
            'email' => 'ivan@example.test',
            'phone' => '+79990000000',
            'participants_count' => 2,
            'participant_names_text' => "Иван Паломник\nМария Паломница",
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'crm_stage' => 'new',
            'priority' => 'high',
            'source' => 'phone',
            'assigned_to' => $admin->id,
        ]);

        $booking = Booking::query()->firstOrFail();

        $response->assertRedirect(route('admin.crm.show', $booking));
        $this->assertSame(2, $booking->participants()->count());
        $this->assertDatabaseHas('booking_participants', [
            'booking_id' => $booking->id,
            'full_name' => 'Мария Паломница',
        ]);
        $this->assertSame(2, $trip->fresh()->booked_count);
        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $booking->id,
            'type' => 'created',
        ]);
    }

    public function test_participant_decision_and_attendance_are_controlled_individually(): void
    {
        $admin = $this->admin();
        $trip = $this->trip();
        $booking = app(BookingCrmService::class)->createBooking($trip, [
            'contact_name' => 'Группа паломников',
            'participants_count' => 2,
            'total_amount' => 4000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'participant_names' => ['Анна', 'Пётр'],
        ], null, $admin);
        $participant = $booking->participants()->where('full_name', 'Пётр')->firstOrFail();

        $this->actingAs($admin)->put('/admin/crm/participants/'.$participant->id, [
            'full_name' => 'Пётр',
            'decision_status' => 'not_going',
            'attendance_status' => 'pending',
            'paid_amount' => 0,
        ])->assertRedirect();

        $this->assertSame('not_going', $participant->fresh()->decision_status);
        $this->assertSame(1, $trip->fresh()->booked_count);

        $primary = $booking->participants()->where('full_name', 'Анна')->firstOrFail();
        $this->actingAs($admin)->put('/admin/crm/participants/'.$primary->id, [
            'full_name' => 'Анна',
            'decision_status' => 'going',
            'attendance_status' => 'attended',
            'paid_amount' => 2000,
        ])->assertRedirect();

        $this->assertSame(1, $booking->fresh()->checked_in_participants);
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_crm_dashboard_reports_and_csv_export_are_available(): void
    {
        $admin = $this->admin();
        $trip = $this->trip();
        app(BookingCrmService::class)->createBooking($trip, [
            'contact_name' => 'Отчётный паломник',
            'participants_count' => 1,
            'total_amount' => 2500,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'source' => 'site',
        ], null, $admin);

        $this->actingAs($admin)
            ->get('/admin/crm')
            ->assertOk()
            ->assertSee('CRM паломнических заявок')
            ->assertSee('Отчётный паломник');

        $this->actingAs($admin)
            ->get('/admin/crm/reports')
            ->assertOk()
            ->assertSee('Отчёты по паломническим заявкам');

        $this->actingAs($admin)
            ->get('/admin/crm/export/bookings')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function trip(): Trip
    {
        $route = PilgrimageRoute::query()->create([
            'title' => 'Тестовый маршрут',
            'slug' => 'test-route-'.uniqid(),
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'base_price' => 2000,
            'is_group' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        return Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'starts_at' => now()->addWeek(),
            'meeting_point' => 'Москва',
            'capacity' => 20,
            'booked_count' => 0,
            'price' => 2000,
            'status' => 'open',
        ]);
    }
}
