<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('site.auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return back()->with('success', 'Если такой email зарегистрирован, на него отправлена ссылка для восстановления пароля.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('site.auth.reset-password', [
  'token' => $token,
  'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
  'token' => ['required', 'string'],
  'email' => ['required', 'email'],
  'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
  [
      'email' => mb_strtolower($data['email']),
      'password' => $data['password'],
      'password_confirmation' => $request->input('password_confirmation'),
      'token' => $data['token'],
  ],
  function ($user, string $password) {
      $user->forceFill([
'password' => Hash::make($password),
      ])->setRememberToken(Str::random(60));
      $user->save();
      event(new PasswordReset($user));
  }
        );

        if ($status !== Password::PASSWORD_RESET) {
  return back()->withInput($request->only('email'))
      ->withErrors(['email' => 'Ссылка недействительна или устарела. Запросите восстановление ещё раз.']);
        }

        return redirect()->route('login')->with('success', 'Пароль изменён. Войдите с новым паролем.');
    }
}
