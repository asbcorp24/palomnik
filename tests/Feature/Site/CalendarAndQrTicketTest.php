<?php

namespace Tests\Feature\Site;

use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAndQrTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_shows_only_published_events_and_exports_ics(): void
    {
        $published = CalendarEvent::query()->create([
            'title' => 'Престольный праздник',
            'slug' => 'patronal-feast-test',
            'type' => 'feast',
            'short_description' => 'Праздничное богослужение.',
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(12, 0),
            'location' => 'Тестовый храм',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        CalendarEvent::query()->create([
            'title' => 'Скрытое событие',
            'slug' => 'hidden-event-test',
            'type' => 'other',
            'starts_at' => now()->addDays(4),
            'is_published' => false,
        ]);

        $this->get('/calendar?month='.$published->starts_at->format('Y-m'))
            ->assertOk()
            ->assertSee('Престольный праздник')
            ->assertDontSee('Скрытое событие');

        $this->get('/calendar/'.$published->slug.'/ics')
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=UTF-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('SUMMARY:Престольный праздник');
    }

    public function test_admin_can_create_calendar_event(): void
    {
        $admin = $this->user('calendar-admin@example.test', User::ROLE_ADMIN);

        $this->actingAs($admin)->post('/admin/calendar', [
            'title' => 'Крестный ход',
            'type' => 'procession',
            'short_description' => 'Общегородской крестный ход.',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
            'location' => 'Москва',
            'is_published' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Крестный ход',
            'type' => 'procession',
            'is_published' => true,
            'created_by' => $admin->id,
        ]);
    }

    public function test_owner_can_open_qr_ticket_and_service_manager_can_check_it_in_once(): void
    {
        $pilgrim = $this->user('ticket-owner@example.test');
        $scanner = $this->user('scanner@example.test', User::ROLE_SERVICE_MANAGER);
        $route = PilgrimageRoute::query()->create([
            'title' => 'Тестовый маршрут',
            'slug' => 'test-ticket-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'is_group' => true,
            'is_published' => true,
        ]);
        $trip = Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'starts_at' => now()->addDays(2),
            'meeting_point' => 'Метро ВДНХ',
            'capacity' => 20,
            'booked_count' => 2,
            'price' => 1000,
            'status' => 'open',
        ]);
        $booking = Booking::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $pilgrim->id,
            'contact_name' => $pilgrim->name,
            'email' => $pilgrim->email,
            'phone' => '+70000000000',
            'participants_count' => 2,
            'total_amount' => 2000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'ticket_code' => 'MP-TEST-001',
        ]);

        $this->assertNotEmpty($booking->ticket_token);

        $this->actingAs($pilgrim)
            ->get('/bookings/'.$booking->id.'/ticket')
            ->assertOk()
            ->assertSee('MP-TEST-001')
            ->assertSee('QR-код');

        $this->actingAs($scanner)
            ->getJson('/service/tickets/lookup?token='.$booking->ticket_token)
            ->assertOk()
            ->assertJsonPath('ticket_code', 'MP-TEST-001')
            ->assertJsonPath('is_checked_in', false);

        $this->actingAs($scanner)
            ->postJson('/service/tickets/check-in', [
                'token' => $booking->ticket_token,
                'participants' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('booking.is_checked_in', true);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'checked_in_by' => $scanner->id,
            'checked_in_participants' => 2,
        ]);

        $this->actingAs($scanner)
            ->postJson('/service/tickets/check-in', [
                'token' => $booking->ticket_token,
                'participants' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_another_pilgrim_cannot_open_someone_elses_ticket(): void
    {
        $owner = $this->user('ticket-owner2@example.test');
        $other = $this->user('ticket-other@example.test');
        $route = PilgrimageRoute::query()->create([
            'title' => 'Закрытый билет',
            'slug' => 'private-ticket-route',
            'category' => 'one_day',
            'difficulty' => 'easy',
            'duration_days' => 1,
            'is_group' => true,
            'is_published' => true,
        ]);
        $trip = Trip::query()->create([
            'pilgrimage_route_id' => $route->id,
            'starts_at' => now()->addDays(2),
            'status' => 'open',
        ]);
        $booking = Booking::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
            'contact_name' => $owner->name,
            'email' => $owner->email,
            'phone' => '+70000000001',
            'participants_count' => 1,
            'total_amount' => 0,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'ticket_code' => 'MP-TEST-PRIVATE',
        ]);

        $this->actingAs($other)
            ->get('/bookings/'.$booking->id.'/ticket')
            ->assertForbidden();
    }

    private function user(string $email, string $role = User::ROLE_PILGRIM): User
    {
        return User::query()->create([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'password' => bcrypt('Password123'),
            'role' => $role,
            'is_active' => true,
            'preferences' => [],
        ]);
    }
}
