<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VkEmailVerificationBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_vk_user_does_not_need_email_verification(): void
    {
        Route::middleware(['web', 'auth', 'verified'])
            ->get('/__test/vk-verified', fn () => response('ok'));

        $user = User::factory()->unverified()->create([
            'vk_id' => '7063816',
            'email' => 'vk_7063816@users.palomnik.invalid',
        ]);

        $this->assertTrue($user->hasVerifiedEmail());

        Notification::fake();
        $user->sendEmailVerificationNotification();
        Notification::assertNothingSent();

        $this->actingAs($user)
            ->get('/__test/vk-verified')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_regular_unverified_user_still_needs_email_verification(): void
    {
        Route::middleware(['web', 'auth', 'verified'])
            ->get('/__test/email-verified', fn () => response('ok'));

        $user = User::factory()->unverified()->create([
            'vk_id' => null,
        ]);

        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->get('/__test/email-verified')
            ->assertRedirect(route('verification.notice'));
    }
}
