@extends('site.layouts.app')

@section('title', 'Вход — Московский паломник')

@section('content')
<section class="auth-section">
    <div class="container">
        <div class="auth-card mx-auto" style="max-width:980px">
  <div class="row g-0">
      <div class="col-lg-5">
<div class="auth-aside">
    <div class="section-kicker text-warning mb-3">Личный кабинет</div>
    <h1 class="h2 mb-4">Ваш путь сохраняется</h1>
    <p class="opacity-75 mb-4">После входа доступны избранные места, бронирования, посещения, достижения и собственные маршруты.</p>
    <div class="auth-feature"><i class="bi bi-heart"></i><div><strong>Избранное</strong><div class="small opacity-75">Сохраняйте храмы в персональные списки.</div></div></div>
    <div class="auth-feature"><i class="bi bi-ticket-perforated"></i><div><strong>Бронирования</strong><div class="small opacity-75">Следите за поездками и электронными билетами.</div></div></div>
    <div class="auth-feature"><i class="bi bi-trophy"></i><div><strong>Достижения</strong><div class="small opacity-75">Отмечайте посещения и проходите квесты.</div></div></div>
</div>
      </div>
      <div class="col-lg-7">
<div class="auth-form">
    <h2 class="h3 mb-2">Вход</h2>
    <p class="text-secondary mb-4">Войдите по email и паролю или используйте VK ID.</p>

    <a class="btn w-100 py-3 text-white mb-4" style="background:#07f" href="{{ route('auth.vk.redirect') }}">
        <strong class="me-2">VK</strong>Войти через VK ID
    </a>

    <div class="d-flex align-items-center gap-3 mb-4"><hr class="flex-grow-1"><span class="small text-secondary">или по email</span><hr class="flex-grow-1"></div>

    <form method="POST" action="{{ route('login.submit') }}">
        @csrf
        <div class="mb-3">
  <label class="form-label" for="email">Email</label>
  <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
  @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-2">
  <label class="form-label" for="password">Пароль</label>
  <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="current-password">
  @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
  <div class="form-check">
      <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
      <label class="form-check-label" for="remember">Запомнить меня</label>
  </div>
  <a class="small" href="{{ route('password.request') }}">Забыли пароль?</a>
        </div>
        <button class="btn btn-pm-gold w-100 py-3" type="submit">Войти</button>
    </form>

    <div class="small text-secondary text-center mt-3">Продолжая вход через VK, вы принимаете <a href="{{ route('terms') }}">правила сервиса</a> и <a href="{{ route('privacy') }}">политику обработки данных</a>.</div>
    <div class="text-center text-secondary mt-4">Нет аккаунта? <a class="fw-semibold" href="{{ route('register') }}">Зарегистрироваться</a></div>
    <div class="text-center mt-3"><a class="small text-secondary" href="{{ route('admin.login') }}">Вход для администратора</a></div>
</div>
      </div>
  </div>
        </div>
    </div>
</section>
@endsection
