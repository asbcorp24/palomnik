@extends('admin.layouts.app')

@section('title', ($item->exists ? 'Редактирование' : 'Создание').' — '.$config['title'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.modules.index', $resource) }}"><i class="bi bi-arrow-left me-1"></i>{{ $config['title'] }}</a>
        <h1 class="page-title mt-2">{{ $item->exists ? 'Редактирование' : 'Создание' }}: {{ $config['single'] }}</h1>
        <div class="page-subtitle">Заполните данные и сохраните изменения.</div>
    </div>
</div>

<form method="POST" action="{{ $item->exists ? route('admin.modules.update', [$resource, $item->getKey()]) : route('admin.modules.store', $resource) }}">
    @csrf
    @if($item->exists) @method('PUT') @endif

    @if($resource === 'routes')
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card-soft p-4 mb-4">
                    <h2 class="h5 mb-4">Основные данные маршрута</h2>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required" for="title">Название</label>
                            <input class="form-control" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="slug">URL-имя</label>
                            <input class="form-control" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" maxlength="255" placeholder="Заполнится автоматически">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="category">Категория</label>
                            <select class="form-select" id="category" name="category" required>
                                @foreach($options['route_categories'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category', $item->category ?: 'one_day') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="difficulty">Сложность</label>
                            <select class="form-select" id="difficulty" name="difficulty" required>
                                @foreach($options['difficulties'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('difficulty', $item->difficulty ?: 'easy') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="duration_days">Дней</label>
                            <input class="form-control" id="duration_days" type="number" min="1" max="365" name="duration_days" value="{{ old('duration_days', $item->duration_days ?: 1) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="duration_minutes">Общее время, минут</label>
                            <input class="form-control" id="duration_minutes" type="number" min="1" name="duration_minutes" value="{{ old('duration_minutes', $item->duration_minutes) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="base_price">Базовая цена, ₽</label>
                            <input class="form-control" id="base_price" type="number" min="0" step="0.01" name="base_price" value="{{ old('base_price', $item->base_price) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="short_description">Краткое описание</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="3">{{ old('short_description', $item->short_description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Полное описание</label>
                            <textarea class="form-control" id="description" name="description" rows="7">{{ old('description', $item->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="program">Программа по дням и точкам</label>
                            <textarea class="form-control" id="program" name="program" rows="9" placeholder="День 1...">{{ old('program', $item->program) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card-soft p-4">
                    <h2 class="h5 mb-2">Точки маршрута</h2>
                    <div class="small text-secondary mb-4">Выберите храмы и другие объекты. Порядок сейчас соответствует порядку выбора; отдельная сортировка точек будет добавлена в конструкторе маршрута.</div>
                    <select class="form-select" name="object_ids[]" multiple size="14">
                        @foreach($options['objects'] as $object)
                            <option value="{{ $object->id }}" @selected(in_array($object->id, old('object_ids', $selectedObjectIds)))>{{ $object->name }} — {{ $object->address }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card-soft p-4 position-sticky" style="top:100px">
                    <h2 class="h5 mb-4">Публикация</h2>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_group" value="0">
                        <input class="form-check-input" id="is_group" type="checkbox" name="is_group" value="1" @checked((bool)old('is_group', $item->is_group))>
                        <label class="form-check-label" for="is_group">Групповой маршрут</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_published" value="0">
                        <input class="form-check-input" id="is_published" type="checkbox" name="is_published" value="1" @checked((bool)old('is_published', $item->is_published))>
                        <label class="form-check-label" for="is_published">Опубликован</label>
                    </div>
                    <label class="form-label" for="published_at">Дата публикации</label>
                    <input class="form-control mb-4" id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at', $item->published_at ? $item->published_at->format('Y-m-d\TH:i') : '') }}">
                    <button class="btn btn-gold w-100 py-3" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить маршрут</button>
                </div>
            </div>
        </div>

    @elseif($resource === 'trips')
        <div class="card-soft p-4" style="max-width:980px">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label required" for="pilgrimage_route_id">Маршрут</label>
                    <select class="form-select" id="pilgrimage_route_id" name="pilgrimage_route_id" required>
                        <option value="">Выберите маршрут</option>
                        @foreach($options['routes'] as $route)
                            <option value="{{ $route->id }}" @selected((int)old('pilgrimage_route_id', $item->pilgrimage_route_id) === $route->id)>{{ $route->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="title">Дополнительное название поездки</label>
                    <input class="form-control" id="title" name="title" value="{{ old('title', $item->title) }}" placeholder="Например, праздничная поездка">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="starts_at">Начало</label>
                    <input class="form-control" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $item->starts_at ? $item->starts_at->format('Y-m-d\TH:i') : '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Окончание</label>
                    <input class="form-control" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $item->ends_at ? $item->ends_at->format('Y-m-d\TH:i') : '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="meeting_point">Место сбора</label>
                    <input class="form-control" id="meeting_point" name="meeting_point" value="{{ old('meeting_point', $item->meeting_point) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="capacity">Количество мест</label>
                    <input class="form-control" id="capacity" type="number" min="1" name="capacity" value="{{ old('capacity', $item->capacity) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="price">Цена, ₽</label>
                    <input class="form-control" id="price" type="number" min="0" step="0.01" name="price" value="{{ old('price', $item->price) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="status">Статус</label>
                    <select class="form-select" id="status" name="status" required>
                        @foreach($options['trip_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $item->status ?: 'open') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Примечания организатора</label>
                    <textarea class="form-control" id="notes" name="notes" rows="5">{{ old('notes', $item->notes) }}</textarea>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить поездку</button>
                <a class="btn btn-light px-4" href="{{ route('admin.modules.index', $resource) }}">Отмена</a>
            </div>
        </div>

    @else
        <div class="card-soft p-4" style="max-width:980px">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="title">Название достижения</label>
                    <input class="form-control" id="title" name="title" value="{{ old('title', $item->title) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="slug">URL-имя</label>
                    <input class="form-control" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" placeholder="Автоматически">
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="category">Категория</label>
                    <select class="form-select" id="category" name="category" required>
                        @foreach($options['achievement_categories'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('category', $item->category ?: 'visits') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="badge_level">Уровень значка</label>
                    <select class="form-select" id="badge_level" name="badge_level" required>
                        @foreach($options['badge_levels'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('badge_level', $item->badge_level ?: 'special') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="points">Баллы</label>
                    <input class="form-control" id="points" type="number" min="0" name="points" value="{{ old('points', $item->points ?: 0) }}" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label required" for="condition_type">Тип условия</label>
                    <input class="form-control" id="condition_type" name="condition_type" value="{{ old('condition_type', $item->condition_type ?: 'visits_count') }}" required placeholder="visits_count">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="condition_value">Значение</label>
                    <input class="form-control" id="condition_value" type="number" min="0" name="condition_value" value="{{ old('condition_value', $item->condition_value) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="icon">Bootstrap Icon</label>
                    <input class="form-control" id="icon" name="icon" value="{{ old('icon', $item->icon) }}" placeholder="bi-trophy">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Описание и условие получения</label>
                    <textarea class="form-control" id="description" name="description" rows="6">{{ old('description', $item->description) }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $item->exists ? $item->is_active : true))>
                        <label class="form-check-label" for="is_active">Достижение активно</label>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить достижение</button>
                <a class="btn btn-light px-4" href="{{ route('admin.modules.index', $resource) }}">Отмена</a>
            </div>
        </div>
    @endif
</form>
@endsection
