@extends('site.layouts.app')

@section('title', 'Регистрация — Московский паломник')

@section('content')
<section class="auth-section">
    <div class="container">
        <div class="auth-card mx-auto" style="max-width:1080px">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="auth-aside">
                        <div class="section-kicker text-warning mb-3">Сообщество паломников</div>
                        <h1 class="h2 mb-4">Создайте личный кабинет</h1>
                        <p class="opacity-75">Данные пользователя нужны, чтобы хранить историю поездок, посещений, достижений, отзывов, избранного и персональных маршрутов.</p>
                        <div class="auth-feature"><i class="bi bi-shield-check"></i><div><strong>Персональные данные</strong><div class="small opacity-75">Настройки приватности и уведомлений находятся в профиле.</div></div></div>
                        <div class="auth-feature"><i class="bi bi-map"></i><div><strong>Собственные маршруты</strong><div class="small opacity-75">Собирайте путь из выбранных храмов и святынь.</div></div></div>
                        <div class="auth-feature"><i class="bi bi-people"></i><div><strong>Участие в сообществе</strong><div class="small opacity-75">Публикуйте отзывы, фотографии и путевые заметки.</div></div></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="auth-form">
                        <h2 class="h3 mb-2">Регистрация</h2>
                        <p class="text-secondary mb-4">Заполните основные данные. Остальные настройки можно изменить позднее.</p>

                        <form method="POST" action="{{ route('register.submit') }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="name">Имя</label>
                                    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email</label>
                                    <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="phone">Телефон</label>
                                    <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+7 ..." autocomplete="tel">
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Пароль</label>
                                    <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="new-password">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Повторите пароль</label>
                                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input @error('consent') is-invalid @enderror" id="consent" name="consent" type="checkbox" value="1" @checked(old('consent')) required>
                                        <label class="form-check-label small" for="consent">Я согласен на обработку персональных данных и принимаю правила сервиса.</label>
                                        @error('consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-pm-gold w-100 py-3 mt-4" type="submit">Создать аккаунт</button>
                        </form>

                        <div class="text-center text-secondary mt-4">Уже зарегистрированы? <a class="fw-semibold" href="{{ route('login') }}">Войти</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
