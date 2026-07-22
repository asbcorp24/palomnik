@extends('site.layouts.app')

@section('title', 'Восстановление пароля — Московский паломник')

@section('content')
<section class="auth-section">
    <div class="container">
        <div class="auth-card mx-auto" style="max-width:720px">
  <div class="auth-form">
      <h1 class="h3 mb-2">Восстановление пароля</h1>
      <p class="text-secondary mb-4">Укажите email аккаунта. Мы отправим ссылку для создания нового пароля.</p>

      <form method="POST" action="{{ route('password.email') }}">
@csrf
<label class="form-label" for="email">Email</label>
<input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
<button class="btn btn-pm-gold w-100 py-3 mt-4" type="submit">Отправить ссылку</button>
      </form>

      <div class="text-center mt-4"><a href="{{ route('login') }}">Вернуться ко входу</a></div>
  </div>
        </div>
    </div>
</section>
@endsection
