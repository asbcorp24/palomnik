@extends('site.profile.layout')

@section('title', 'Настройки профиля — Московский паломник')
@section('profile_title', 'Настройки профиля')
@section('profile_subtitle', 'Контактные данные, приватность, уведомления и доступность интерфейса.')

@section('profile_content')
<form class="profile-card" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="d-flex align-items-center gap-4 mb-5">
        <div class="profile-avatar" style="width:100px;height:100px">
            @if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">@else{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}@endif
        </div>
        <div class="flex-grow-1">
            <label class="form-label" for="avatar">Фотография профиля</label>
            <input class="form-control @error('avatar') is-invalid @enderror" id="avatar" name="avatar" type="file" accept="image/*">
            <div class="form-text">JPG, PNG или WEBP, до 4 МБ.</div>
            @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="section-kicker mb-2">Основные данные</div>
    <h2 class="h4 mb-4">Профиль</h2>
    <div class="row g-3 mb-5">
        <div class="col-md-6">
            <label class="form-label" for="name">Имя</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="birth_date">Дата рождения</label>
            <input class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($user->birth_date)->format('Y-m-d')) }}">
            @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="phone">Телефон</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @php($preferences = $user->preferences ?: [])
    <div class="section-kicker mb-2">Персонализация</div>
    <h2 class="h4 mb-4">Интерфейс и приватность</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label" for="theme">Тема</label>
            <select class="form-select" id="theme" name="theme">
                @foreach(['light' => 'Светлая', 'dark' => 'Тёмная', 'system' => 'Как в системе'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('theme', $preferences['theme'] ?? 'light') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="font_size">Размер шрифта</label>
            <select class="form-select" id="font_size" name="font_size">
                @foreach(['normal' => 'Обычный', 'large' => 'Крупный', 'extra_large' => 'Очень крупный'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('font_size', $preferences['font_size'] ?? 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="privacy">Видимость профиля</label>
            <select class="form-select" id="privacy" name="privacy">
                @foreach(['private' => 'Только мне', 'registered' => 'Пользователям', 'public' => 'Всем'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('privacy', $preferences['privacy'] ?? 'private') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="notifications" value="0">
                <input class="form-check-input" id="notifications" name="notifications" type="checkbox" value="1" @checked(old('notifications', $preferences['notifications'] ?? true))>
                <label class="form-check-label" for="notifications">Получать уведомления о поездках, маршрутах и достижениях</label>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <label class="form-label d-block">Интересы</label>
        @foreach(['temples' => 'Храмы', 'monasteries' => 'Монастыри', 'history' => 'История', 'family' => 'Семейные маршруты', 'youth' => 'Молодёжные события', 'saints' => 'Святыни'] as $value => $label)
            <div class="form-check form-check-inline mb-2">
                <input class="form-check-input" id="interest_{{ $value }}" name="interests[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, old('interests', $preferences['interests'] ?? []), true))>
                <label class="form-check-label" for="interest_{{ $value }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>

    <div class="section-kicker mb-2">Безопасность</div>
    <h2 class="h4 mb-4">Изменить пароль</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label" for="current_password">Текущий пароль</label><input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password">@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="password">Новый пароль</label><input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="password_confirmation">Повторите пароль</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password"></div>
    </div>

    <button class="btn btn-pm-gold px-5 py-3" type="submit">Сохранить настройки</button>
</form>
@endsection
