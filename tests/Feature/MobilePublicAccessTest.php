<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_mobile_sections_do_not_require_authentication(): void
    {
        $this->getJson('/api/v1/mobile/home')->assertOk();
        $this->getJson('/api/v1/mobile/routes')->assertOk();
        $this->getJson('/api/v1/mobile/calendar')->assertOk();
        $this->getJson('/api/v1/mobile/community')->assertOk();
        $this->getJson('/api/v1/mobile/community/photos')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
        $this->getJson('/api/v1/mobile/together')->assertOk();
    }

    public function test_personal_mobile_sections_still_require_authentication(): void
    {
        $this->getJson('/api/v1/mobile/profile')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/favorites')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/bookings')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/posts')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/media')->assertUnauthorized();
    }
}
