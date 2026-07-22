<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_wrong_captcha(): void
    {
        $this->withSession(['registration_captcha_answer' => 7])
  ->post('/register', [
      'name' => 'Тестовый паломник',
      'email' => 'captcha@example.test',
      'password' => 'password123',
      'password_confirmation' => 'password123',
      'captcha' => 6,
      'consent' => 1,
  ])
  ->assertSessionHasErrors('captcha');

        $this->assertDatabaseMissing('users', ['email' => 'captcha@example.test']);
    }

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->withSession(['registration_captcha_answer' => 7])
  ->post('/register', [
      'name' => 'Тестовый паломник',
      'email' => 'pilgrim@example.test',
      'password' => 'password123',
      'password_confirmation' => 'password123',
      'captcha' => 7,
      'consent' => 1,
  ])
  ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'pilgrim@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_signed_email_link_verifies_user_without_browser_session(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
  'id' => $user->id,
  'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_is_sent_to_verification_notice_after_login(): void
    {
        $user = User::factory()->unverified()->create([
  'password' => bcrypt('password123'),
  'is_active' => true,
        ]);

        $this->post('/login', [
  'email' => $user->email,
  'password' => 'password123',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_password_reset_request_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
  ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_vk_login_without_configuration_returns_readable_error(): void
    {
        config()->set('services.vkid.client_id', null);
        config()->set('services.vkid.client_secret', null);

        $this->get('/auth/vk')
  ->assertRedirect(route('login'))
  ->assertSessionHas('error');
    }
}
