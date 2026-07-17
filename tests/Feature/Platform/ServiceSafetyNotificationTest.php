<?php

namespace Tests\Feature\Platform;

use App\Models\CommunityReport;
use App\Models\ObjectRepresentative;
use App\Models\ObjectType;
use App\Models\ObjectUpdateRequest;
use App\Models\PilgrimageObject;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\PlatformNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSafetyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_representative_can_submit_object_changes_for_review(): void
    {
        $representative = $this->user('representative@example.test', User::ROLE_OBJECT_EDITOR);
        $object = $this->object();

        ObjectRepresentative::query()->create([
            'pilgrimage_object_id' => $object->id,
            'user_id' => $representative->id,
            'role' => 'editor',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $this->actingAs($representative)
            ->put('/service/objects/'.$object->slug, [
                'short_description' => 'Обновлённое краткое описание',
                'description' => 'Новое полное описание объекта.',
                'history' => 'История объекта.',
                'address' => 'Новый адрес',
                'latitude' => 55.7558,
                'longitude' => 37.6176,
                'phone' => '+7 495 000-00-00',
                'email' => 'temple@example.test',
                'website' => 'https://example.test',
                'schedule_text' => 'Ежедневно с 08:00',
                'parking_info' => 'Есть парковка',
                'accessibility_info' => 'Есть пандус',
                'sanctity_ids' => [],
            ])
            ->assertRedirect();

        $this->assertSame('Старый адрес', $object->fresh()->address);
        $this->assertDatabaseHas('object_update_requests', [
            'pilgrimage_object_id' => $object->id,
            'user_id' => $representative->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_representative_changes(): void
    {
        $admin = $this->user('admin-service@example.test', User::ROLE_ADMIN);
        $representative = $this->user('editor-service@example.test', User::ROLE_OBJECT_EDITOR);
        $object = $this->object();

        $updateRequest = ObjectUpdateRequest::query()->create([
            'pilgrimage_object_id' => $object->id,
            'user_id' => $representative->id,
            'payload' => [
                'address' => 'Одобренный новый адрес',
                'latitude' => 55.7,
                'longitude' => 37.6,
                'short_description' => 'Обновлено',
                'sanctity_ids' => [],
            ],
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->put('/admin/service-review/requests/'.$updateRequest->id, [
                'status' => 'approved',
                'review_note' => 'Проверено.',
            ])
            ->assertRedirect();

        $this->assertSame('Одобренный новый адрес', $object->fresh()->address);
        $this->assertDatabaseHas('object_update_requests', [
            'id' => $updateRequest->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_regular_user_cannot_open_service_cabinet(): void
    {
        $pilgrim = $this->user('pilgrim-service@example.test');

        $this->actingAs($pilgrim)
            ->get('/service')
            ->assertForbidden();
    }

    public function test_user_can_report_and_block_another_user(): void
    {
        $reporter = $this->user('reporter@example.test');
        $reported = $this->user('reported@example.test');

        $this->actingAs($reporter)
            ->post('/safety/reports', [
                'reported_user_id' => $reported->id,
                'category' => 'abuse',
                'description' => 'Пользователь отправляет оскорбительные сообщения в обсуждении.',
            ])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->post('/safety/blocks/'.$reported->id)
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $reporter->id,
            'blocked_id' => $reported->id,
        ]);
    }

    public function test_notification_center_marks_notification_as_read(): void
    {
        $user = $this->user('notifications@example.test');
        $user->notify(new PlatformNotification('Тест', 'Проверка уведомления.', '/profile'));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Проверка уведомления.');

        $this->actingAs($user)
            ->put('/notifications/'.$notification->id.'/read')
            ->assertRedirect('/profile');

        $this->assertNotNull($notification->fresh()->read_at);
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

    private function object(): PilgrimageObject
    {
        $type = ObjectType::query()->create([
            'name' => 'Храм',
            'slug' => 'temple-service-test-'.uniqid(),
            'sort_order' => 10,
        ]);

        return PilgrimageObject::query()->create([
            'object_type_id' => $type->id,
            'name' => 'Тестовый храм',
            'slug' => 'service-temple-'.uniqid(),
            'address' => 'Старый адрес',
            'latitude' => 55.75,
            'longitude' => 37.61,
            'is_published' => true,
        ]);
    }
}
