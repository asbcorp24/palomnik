@extends('site.layouts.app')

@section('title', ($item->exists ? 'Редактирование' : 'Новое совместное паломничество').' — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('together.index') }}">Паломничество вместе</a></li><li class="breadcrumb-item active">{{ $item->exists ? 'Редактирование' : 'Новое предложение' }}</li></ol></nav>
        <div class="section-kicker mb-2">Совместная поездка</div>
        <h1 class="section-title mb-3">{{ $item->exists ? 'Изменить предложение' : 'Предложить паломничество вместе' }}</h1>
        <p class="section-lead mb-0">После создания предложение проходит модерацию. Контакт организатора видят только подтверждённые участники.</p>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container" style="max-width:980px">
        <form class="filter-card p-4 p-lg-5" method="POST" action="{{ $item->exists ? route('together.update', $item) : route('together.store') }}">
            @csrf
            @if($item->exists)@method('PUT')@endif

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label" for="title">Название совместного паломничества</label>
                    <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $item->title) }}" placeholder="Например: Вместе в Троице-Сергиеву лавру" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="pilgrimage_route_id">Готовый маршрут</label>
                    <select class="form-select @error('pilgrimage_route_id') is-invalid @enderror" id="pilgrimage_route_id" name="pilgrimage_route_id">
                        <option value="">Без привязки к готовому маршруту</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}" @selected((string)old('pilgrimage_route_id', $item->pilgrimage_route_id) === (string)$route->id)>{{ $route->title }}</option>
                        @endforeach
                    </select>
                    @error('pilgrimage_route_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Описание и план</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="7" required placeholder="Куда идём, что хотим посетить, кому подойдёт поездка, что взять с собой">{{ old('description', $item->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="starts_at">Начало</label>
                    <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', optional($item->starts_at)->format('Y-m-d\TH:i')) }}" required>
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Окончание, если известно</label>
                    <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', optional($item->ends_at)->format('Y-m-d\TH:i')) }}">
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="meeting_place">Место встречи</label>
                    <input class="form-control @error('meeting_place') is-invalid @enderror" id="meeting_place" name="meeting_place" value="{{ old('meeting_place', $item->meeting_place) }}" placeholder="Например: метро ВДНХ, выход №1" required>
                    @error('meeting_place')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="transport_mode">Транспорт</label>
                    <select class="form-select @error('transport_mode') is-invalid @enderror" id="transport_mode" name="transport_mode" required>
                        @foreach($transportModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('transport_mode', $item->transport_mode ?: 'public') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('transport_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="max_participants">Максимум участников</label>
                    <input class="form-control @error('max_participants') is-invalid @enderror" id="max_participants" name="max_participants" type="number" min="2" max="200" value="{{ old('max_participants', $item->max_participants) }}" placeholder="Без ограничения">
                    @error('max_participants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="join_mode">Как принимать участников</label>
                    <select class="form-select @error('join_mode') is-invalid @enderror" id="join_mode" name="join_mode" required>
                        @foreach($joinModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('join_mode', $item->join_mode ?: 'approval') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('join_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="contact_method">Способ связи после подтверждения</label>
                    <select class="form-select @error('contact_method') is-invalid @enderror" id="contact_method" name="contact_method">
                        <option value="">Не указан</option>
                        @foreach($contactMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('contact_method', $item->contact_method) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('contact_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-7">
                    <label class="form-label" for="contact_value">Контакт</label>
                    <input class="form-control @error('contact_value') is-invalid @enderror" id="contact_value" name="contact_value" value="{{ old('contact_value', $item->contact_value) }}" placeholder="Телефон или имя пользователя в мессенджере">
                    @error('contact_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Контакт не показывается гостям и неподтверждённым участникам.</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-5">
                <button class="btn btn-pm-gold px-4" type="submit"><i class="bi bi-check-lg me-2"></i>{{ $item->exists ? 'Сохранить изменения' : 'Отправить на модерацию' }}</button>
                <a class="btn btn-light px-4" href="{{ $item->exists ? route('together.show', $item) : route('together.index') }}">Отмена</a>
            </div>
        </form>
    </div>
</section>
@endsection
