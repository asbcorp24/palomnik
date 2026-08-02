<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRolePermissionsTest extends TestCase
{
    public function test_pilgrim_has_no_staff_permissions(): void
    {
        $user = $this->user(User::ROLE_PILGRIM);

        $this->assertFalse($user->canAccessBackoffice());
        $this->assertFalse($user->canManageObjects());
        $this->assertFalse($user->hasPermission(User::PERMISSION_MODERATION_MANAGE));
    }

    public function test_object_editor_only_manages_assigned_objects(): void
    {
        $user = $this->user(User::ROLE_OBJECT_EDITOR);

        $this->assertTrue($user->canManageObjects());
        $this->assertFalse($user->canAccessBackoffice());
        $this->assertFalse($user->hasPermission(User::PERMISSION_BOOKINGS_MANAGE));
    }

    public function test_service_manager_manages_routes_trips_bookings_and_tickets(): void
    {
        $user = $this->user(User::ROLE_SERVICE_MANAGER);

        $this->assertTrue($user->canAccessBackoffice());
        $this->assertTrue($user->canManageModule('routes'));
        $this->assertTrue($user->canManageModule('trips'));
        $this->assertTrue($user->hasPermission(User::PERMISSION_BOOKINGS_MANAGE));
        $this->assertFalse($user->hasPermission(User::PERMISSION_MODERATION_MANAGE));
        $this->assertFalse($user->hasPermission(User::PERMISSION_CONTENT_MANAGE));
    }

    public function test_moderator_only_receives_moderation_backoffice_access(): void
    {
        $user = $this->user(User::ROLE_MODERATOR);

        $this->assertTrue($user->canAccessBackoffice());
        $this->assertTrue($user->hasPermission(User::PERMISSION_MODERATION_MANAGE));
        $this->assertFalse($user->hasPermission(User::PERMISSION_CONTENT_MANAGE));
        $this->assertFalse($user->hasPermission(User::PERMISSION_USERS_VIEW));
        $this->assertFalse($user->canManageModule('routes'));
    }

    public function test_admin_manages_content_moderation_and_non_admin_users(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $pilgrim = $this->user(User::ROLE_PILGRIM);
        $otherAdmin = $this->user(User::ROLE_ADMIN);

        $this->assertTrue($admin->hasPermission(User::PERMISSION_CONTENT_MANAGE));
        $this->assertTrue($admin->hasPermission(User::PERMISSION_MODERATION_MANAGE));
        $this->assertTrue($admin->canManageUser($pilgrim));
        $this->assertFalse($admin->canManageUser($otherAdmin));
        $this->assertFalse($admin->hasPermission(User::PERMISSION_SYSTEM_MANAGE));
    }

    public function test_super_admin_has_every_permission_and_can_manage_admins(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $admin = $this->user(User::ROLE_ADMIN);

        $this->assertTrue($superAdmin->hasPermission(User::PERMISSION_SYSTEM_MANAGE));
        $this->assertTrue($superAdmin->hasPermission('future.permission'));
        $this->assertTrue($superAdmin->canManageUser($admin));
        $this->assertTrue($superAdmin->canManageModule('achievements'));
    }

    private function user(string $role): User
    {
        $user = new User();
        $user->role = $role;
        $user->is_active = true;

        return $user;
    }
}
