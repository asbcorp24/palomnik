@extends('site.layouts.app')

@section('title', 'Новый пароль — Московский паломник')

@section('content')
<section class="auth-section">
    <div class="container">
        <div class="auth-card mx-auto" style="max-width:720px">
  <div class="auth-form">
      <h1 class="h3 mb-2">Задайте новый пароль</h1>
      <p class="text-secondary mb-4">Пароль должен содержать не менее восьми символов.</p>

      <form method="POST" action="{{ route('password.update') }}">
@csrf
<input name="token" type="hidden" value="{{ $token }}">

<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="password">Новый пароль</label>
    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="new-password">
    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label" for="password_confirmation">Повторите пароль</label>
    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
</div>

<button class="btn btn-pm-gold w-100 py-3" type="submit">Сохранить новый пароль</button>
      </form>
  </div>
        </div>
    </div>
</section>
@endsection
