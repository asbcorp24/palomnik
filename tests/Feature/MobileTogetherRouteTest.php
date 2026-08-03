<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTogetherRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_my_joint_pilgrimages_without_slug_collision(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/mobile/together/my')
            ->assertOk()
            ->assertJson([
                'organized' => [],
                'memberships' => [],
            ]);
    }

    public function test_my_joint_pilgrimages_requires_authentication(): void
    {
        $this->getJson('/api/v1/mobile/together/my')
            ->assertUnauthorized();
    }
}
