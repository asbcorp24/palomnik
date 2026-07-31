@extends('admin.layouts.app')

@section('title', $point->exists ? 'Редактирование точки интереса' : 'Новая точка интереса')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.points-of-interest.index', $point->pilgrimage_object_id ? ['object_id' => $point->pilgrimage_object_id] : []) }}"><i class="bi bi-arrow-left me-1"></i>Точки интереса</a>
        <h1 class="page-title mt-2">{{ $point->exists ? 'Редактирование точки интереса' : 'Новая точка интереса' }}</h1>
        @if($point->exists)<div class="page-subtitle">{{ $point->name }}</div>@endif
    </div>
</div>

<form method="POST" action="{{ $point->exists ? route('admin.points-of-interest.update', $point) : route('admin.points-of-interest.store') }}">
    @csrf
    @if($point->exists) @method('PUT') @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Привязка и категория</h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label required" for="pilgrimage_object_id">Базовый объект</label>
                        <select class="form-select" id="pilgrimage_object_id" name="pilgrimage_object_id" required>
                            <option value="">Выберите храм или другой объект</option>
                            @foreach($objects as $object)
                                <option
                                    value="{{ $object->id }}"
                                    data-address="{{ $object->address }}"
                                    data-latitude="{{ $object->latitude }}"
                                    data-longitude="{{ $object->longitude }}"
                                    @selected((string)old('pilgrimage_object_id', $point->pilgrimage_object_id) === (string)$object->id)
                                >{{ $object->name }}@if($object->objectType) · {{ $object->objectType->name }}@endif</option>
                            @endforeach
                        </select>
                        <div class="form-text">Точка всегда относится к одному базовому паломническому объекту.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="category">Категория</label>
                        <select class="form-select" id="category" name="category" required>
                            @foreach($categories as $key => $category)
                                <option value="{{ $key }}" @selected(old('category', $point->category ?: 'attraction') === $key)>{{ $category['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Основная информация</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="name">Название</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $point->name) }}" maxlength="255" required placeholder="Например: Парковка у северных ворот">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Описание</label>
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="5000">{{ old('description', $point->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="address">Адрес или ориентир</label>
                        <input class="form-control" id="address" name="address" value="{{ old('address', $point->address) }}" maxlength="500" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="latitude">Широта</label>
                        <input class="form-control" id="latitude" type="number" step="0.0000001" min="-90" max="90" name="latitude" value="{{ old('latitude', $point->latitude) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="longitude">Долгота</label>
                        <input class="form-control" id="longitude" type="number" step="0.0000001" min="-180" max="180" name="longitude" value="{{ old('longitude', $point->longitude) }}" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-outline-green" id="copyBaseLocation" type="button">
                            <i class="bi bi-crosshair me-1"></i>Подставить адрес и координаты базового объекта
                        </button>
                        <div class="form-text">После подстановки скорректируйте координаты до фактического места парковки, кафе или гостиницы.</div>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4">
                <h2 class="h5 mb-4">Контакты и режим работы</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Телефон</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $point->phone) }}" maxlength="64">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="website">Сайт</label>
                        <input class="form-control" id="website" type="url" name="website" value="{{ old('website', $point->website) }}" placeholder="https://">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="schedule_text">Режим работы и примечания</label>
                        <textarea class="form-control" id="schedule_text" name="schedule_text" rows="4" maxlength="3000">{{ old('schedule_text', $point->schedule_text) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-soft p-4 position-sticky" style="top:96px">
                <h2 class="h5 mb-4">Публикация</h2>
                <div class="form-check form-switch mb-4">
                    <input type="hidden" name="is_published" value="0">
                    <input class="form-check-input" id="is_published" type="checkbox" name="is_published" value="1" @checked((bool)old('is_published', $point->exists ? $point->is_published : true))>
                    <label class="form-check-label fw-semibold" for="is_published">Показывать на карте</label>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="sort_order">Порядок</label>
                    <input class="form-control" id="sort_order" type="number" min="0" max="100000" name="sort_order" value="{{ old('sort_order', $point->sort_order ?? 0) }}">
                    <div class="form-text">Меньшее значение отображается раньше.</div>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-gold" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить точку</button>
                    <a class="btn btn-light" href="{{ route('admin.points-of-interest.index', $point->pilgrimage_object_id ? ['object_id' => $point->pilgrimage_object_id] : []) }}">Отмена</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const objectSelect = document.getElementById('pilgrimage_object_id');
    const copyButton = document.getElementById('copyBaseLocation');
    const address = document.getElementById('address');
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');

    copyButton?.addEventListener('click', () => {
        const option = objectSelect?.selectedOptions?.[0];
        if (!option || !option.value) return;
        address.value = option.dataset.address || '';
        latitude.value = option.dataset.latitude || '';
        longitude.value = option.dataset.longitude || '';
    });
})();
</script>
@endpush
