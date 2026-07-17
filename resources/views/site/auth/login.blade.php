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
                        <p class="text-secondary mb-4">Введите email и пароль, указанные при регистрации.</p>

                        <form method="POST" action="{{ route('login.submit') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Пароль</label>
                                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="current-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                                <label class="form-check-label" for="remember">Запомнить меня</label>
                            </div>
                            <button class="btn btn-pm-gold w-100 py-3" type="submit">Войти</button>
                        </form>

                        <div class="text-center text-secondary mt-4">Нет аккаунта? <a class="fw-semibold" href="{{ route('register') }}">Зарегистрироваться</a></div>
                        <div class="text-center mt-3"><a class="small text-secondary" href="{{ route('admin.login') }}">Вход для администратора</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
