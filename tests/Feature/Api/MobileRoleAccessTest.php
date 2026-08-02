<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilgrim_receives_no_staff_workspaces(): void
    {
        $user = $this->user('pilgrim-mobile-role@example.test', User::ROLE_PILGRIM);

        $this->withToken($user->createToken('Flutter test')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_PILGRIM)
            ->assertJsonPath('user.role_label', 'Паломник')
            ->assertJsonPath('user.capabilities.backoffice_access', false)
            ->assertJsonPath('user.capabilities.service_access', false)
            ->assertJsonCount(0, 'user.workspaces');
    }

    public function test_service_manager_receives_crm_ticket_and_service_workspaces(): void
    {
        $user = $this->user('service-mobile-role@example.test', User::ROLE_SERVICE_MANAGER);

        $response = $this->withToken($user->createToken('Flutter test')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_SERVICE_MANAGER)
            ->assertJsonPath('user.role_label', 'Паломническая служба')
            ->assertJsonPath('user.capabilities.backoffice_access', true)
            ->assertJsonPath('user.capabilities.service_access', true)
            ->assertJsonPath('user.capabilities.bookings_manage', true)
            ->assertJsonPath('user.capabilities.routes_manage', true)
            ->assertJsonPath('user.capabilities.trips_manage', true)
            ->assertJsonPath('user.capabilities.moderation_manage', false)
            ->assertJsonCount(2, 'user.workspaces');

        $this->assertSame(
            ['service', 'backoffice'],
            collect($response->json('user.workspaces'))->pluck('code')->all()
        );
    }

    public function test_moderator_profile_contains_only_moderation_backoffice_access(): void
    {
        $user = $this->user('moderator-mobile-role@example.test', User::ROLE_MODERATOR);

        $response = $this->withToken($user->createToken('Flutter test')->plainTextToken)
            ->getJson('/api/v1/mobile/profile')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_MODERATOR)
            ->assertJsonPath('user.role_label', 'Модератор')
            ->assertJsonPath('user.capabilities.backoffice_access', true)
            ->assertJsonPath('user.capabilities.moderation_manage', true)
            ->assertJsonPath('user.capabilities.bookings_manage', false)
            ->assertJsonPath('user.capabilities.service_access', false)
            ->assertJsonCount(1, 'user.workspaces');

        $this->assertSame('backoffice', $response->json('user.workspaces.0.code'));
    }

    private function user(string $email, string $role): User
    {
        return User::query()->create([
            'name' => 'Пользователь мобильного приложения',
            'email' => $email,
            'password' => bcrypt('Password123'),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
            'preferences' => [
                'theme' => 'system',
                'notifications' => true,
            ],
        ]);
    }
}
