<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
  return redirect()->route('profile.dashboard');
        }

        return view('site.auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
  event(new Verified($user));
        }

        if (Auth::check() && Auth::id() === $user->id) {
  return redirect()->route('profile.dashboard')
      ->with('success', 'Email подтверждён. Все возможности личного кабинета доступны.');
        }

        return redirect()->route('login')
  ->with('success', 'Email подтверждён. Теперь можно войти в аккаунт.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
  return redirect()->route('profile.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Новое письмо с подтверждением отправлено.');
    }
}
