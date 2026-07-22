<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FavoriteList;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
  return redirect()->route(Auth::user()->hasVerifiedEmail() ? 'profile.dashboard' : 'verification.notice');
        }

        return view('site.auth.login');
    }

    public function showRegisterForm(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
  return redirect()->route(Auth::user()->hasVerifiedEmail() ? 'profile.dashboard' : 'verification.notice');
        }

        return view('site.auth.register', [
  'captchaQuestion' => $this->createRegistrationCaptcha($request),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
  'name' => ['required', 'string', 'max:255'],
  'email' => ['required', 'email', 'max:255', 'unique:users,email'],
  'phone' => ['nullable', 'string', 'max:64', 'unique:users,phone'],
  'password' => ['required', 'confirmed', Password::min(8)],
  'captcha' => ['required', 'integer'],
  'consent' => ['accepted'],
        ], [
  'captcha.required' => 'Решите простой пример.',
  'captcha.integer' => 'Ответ на пример должен быть числом.',
  'consent.accepted' => 'Необходимо согласиться с обработкой персональных данных.',
        ]);

        $expectedCaptcha = $request->session()->pull('registration_captcha_answer');
        if ($expectedCaptcha === null || (int) $data['captcha'] !== (int) $expectedCaptcha) {
  return back()
      ->withInput($request->except(['password', 'password_confirmation', 'captcha']))
      ->withErrors(['captcha' => 'Неверный ответ. Решите новый пример.']);
        }

        $user = DB::transaction(function () use ($request, $data) {
  $user = User::query()->create([
      'name' => $data['name'],
      'email' => mb_strtolower($data['email']),
      'phone' => ! empty($data['phone']) ? $data['phone'] : null,
      'password' => Hash::make($data['password']),
      'role' => User::ROLE_PILGRIM,
      'is_active' => true,
      'preferences' => [
'notifications' => true,
'privacy' => 'private',
'theme' => 'light',
'font_size' => 'normal',
'interests' => [],
      ],
  ]);

  FavoriteList::query()->create([
      'user_id' => $user->id,
      'name' => 'Избранное',
      'is_default' => true,
  ]);

  $user->consents()->create([
      'type' => 'personal_data_processing',
      'policy_version' => config('palomnik.privacy.policy_version', '1.0'),
      'accepted_at' => now(),
      'ip_address' => $request->ip(),
      'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
  ]);

  return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
  ->with('success', 'Аккаунт создан. Подтвердите email по ссылке из письма.');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
  'email' => ['required', 'email'],
  'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
  'email' => mb_strtolower($credentials['email']),
  'password' => $credentials['password'],
  'is_active' => true,
        ], $request->boolean('remember'))) {
  return back()
      ->withInput($request->only('email'))
      ->withErrors(['email' => 'Неверный email или пароль либо аккаунт заблокирован.']);
        }

        $request->session()->regenerate();

        if (! $request->user()->hasVerifiedEmail()) {
  return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('profile.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Вы вышли из личного кабинета.');
    }

    private function createRegistrationCaptcha(Request $request): string
    {
        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $addition = (bool) random_int(0, 1);

        if (! $addition && $right > $left) {
  [$left, $right] = [$right, $left];
        }

        $answer = $addition ? $left + $right : $left - $right;
        $request->session()->put('registration_captcha_answer', $answer);

        return $left.' '.($addition ? '+' : '−').' '.$right.' = ?';
    }
}
