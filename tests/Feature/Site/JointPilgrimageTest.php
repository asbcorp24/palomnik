<?php

namespace Tests\Feature\Site;

use App\Models\JointPilgrimage;
use App\Models\JointPilgrimageMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JointPilgrimageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_is_available_inside_community(): void
    {
        $this->get('/community/together')
            ->assertOk()
            ->assertSee('Паломничество вместе');

        $this->get('/together')
            ->assertRedirect('/community/together');
    }

    public function test_user_can_create_joint_pilgrimage_for_moderation(): void
    {
        $organizer = $this->user('organizer@example.test');

        $response = $this->actingAs($organizer)->post('/community/together', [
            'title' => 'Вместе в Троице-Сергиеву лавру',
            'description' => 'Собираем небольшую группу для спокойной совместной паломнической поездки.',
            'starts_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(5)->addHours(8)->format('Y-m-d H:i:s'),
            'meeting_place' => 'Москва, метро ВДНХ',
            'max_participants' => 12,
            'transport_mode' => 'public',
            'join_mode' => 'approval',
            'contact_method' => 'telegram',
            'contact_value' => '@organizer',
        ]);

        $item = JointPilgrimage::query()->firstOrFail();
        $response->assertRedirect('/community/together/'.$item->slug);
        $this->assertSame('pending', $item->status);
        $this->assertSame($organizer->id, $item->organizer_id);
    }

    public function test_users_can_join_get_approved_and_discuss_trip(): void
    {
        $organizer = $this->user('organizer2@example.test');
        $participant = $this->user('participant@example.test');
        $item = JointPilgrimage::query()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Совместное паломничество',
            'slug' => 'joint-pilgrimage-test',
            'description' => 'Подробное описание совместной паломнической поездки для небольшой группы.',
            'starts_at' => now()->addWeek(),
            'meeting_place' => 'Москва',
            'max_participants' => 10,
            'transport_mode' => 'public',
            'join_mode' => 'approval',
            'status' => 'published',
        ]);

        $this->actingAs($participant)
            ->post('/community/together/'.$item->slug.'/join', ['message' => 'Хочу присоединиться.'])
            ->assertRedirect();

        $member = JointPilgrimageMember::query()->firstOrFail();
        $this->assertSame('pending', $member->status);

        $this->actingAs($organizer)
            ->put('/community/together/'.$item->slug.'/members/'.$member->id, ['status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('joint_pilgrimage_members', [
            'id' => $member->id,
            'status' => 'approved',
        ]);

        $this->actingAs($participant)
            ->post('/community/together/'.$item->slug.'/messages', ['body' => 'Во сколько встречаемся у метро?'])
            ->assertRedirect();

        $this->assertDatabaseHas('joint_pilgrimage_messages', [
            'joint_pilgrimage_id' => $item->id,
            'user_id' => $participant->id,
            'body' => 'Во сколько встречаемся у метро?',
            'is_system' => false,
        ]);

        $this->actingAs($participant)
            ->get('/community/together/'.$item->slug.'/messages-feed')
            ->assertOk()
            ->assertSee('Во сколько встречаемся у метро?');
    }

    public function test_admin_can_publish_joint_pilgrimage(): void
    {
        $organizer = $this->user('organizer3@example.test');
        $admin = $this->user('admin@example.test', User::ROLE_ADMIN);
        $item = JointPilgrimage::query()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Предложение на модерации',
            'slug' => 'pending-joint-pilgrimage',
            'description' => 'Подробное описание предложения для совместного паломничества.',
            'starts_at' => now()->addWeek(),
            'meeting_place' => 'Москва',
            'transport_mode' => 'walk',
            'join_mode' => 'auto',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->put('/admin/together/'.$item->slug, [
                'status' => 'published',
                'moderation_note' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('joint_pilgrimages', [
            'id' => $item->id,
            'status' => 'published',
            'moderated_by' => $admin->id,
        ]);
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
