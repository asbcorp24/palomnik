@extends('admin.layouts.app')

@section('title', $event->exists ? 'Редактирование события' : 'Новое событие')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><a class="small text-decoration-none text-secondary" href="{{ route('admin.calendar.index') }}"><i class="bi bi-arrow-left me-1"></i>Календарь</a><h1 class="page-title mt-2">{{ $event->exists?'Редактирование события':'Новое событие' }}</h1><div class="page-subtitle">Событие появится на публичном календаре после публикации.</div></div>
    @if($event->exists && $event->is_published)<a class="btn btn-outline-green" href="{{ route('calendar.show',$event) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Открыть на сайте</a>@endif
</div>

<form method="POST" action="{{ $event->exists?route('admin.calendar.update',$event):route('admin.calendar.store') }}">
    @csrf
    @if($event->exists)@method('PUT')@endif
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Основная информация</h2>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="title">Название</label><input class="form-control" id="title" name="title" value="{{ old('title',$event->title) }}" required></div>
                    <div class="col-md-7"><label class="form-label" for="slug">URL-адрес</label><input class="form-control" id="slug" name="slug" value="{{ old('slug',$event->slug) }}" placeholder="Создаётся автоматически"></div>
                    <div class="col-md-5"><label class="form-label required" for="type">Тип события</label><select class="form-select" id="type" name="type" required>@foreach($types as $value=>$label)<option value="{{ $value }}" @selected(old('type',$event->type?:'service')===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label" for="short_description">Краткое описание</label><textarea class="form-control" id="short_description" name="short_description" rows="3" maxlength="2000">{{ old('short_description',$event->short_description) }}</textarea></div>
                    <div class="col-12"><label class="form-label" for="description">Полное описание</label><textarea class="form-control" id="description" name="description" rows="9">{{ old('description',$event->description) }}</textarea></div>
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Дата и место</h2>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label required" for="starts_at">Начало</label><input class="form-control" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at',optional($event->starts_at)->format('Y-m-d\TH:i')) }}" required></div>
                    <div class="col-md-6"><label class="form-label" for="ends_at">Окончание</label><input class="form-control" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at',optional($event->ends_at)->format('Y-m-d\TH:i')) }}"></div>
                    <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="all_day" value="0"><input class="form-check-input" id="all_day" type="checkbox" name="all_day" value="1" @checked((bool)old('all_day',$event->all_day))><label class="form-check-label" for="all_day">Событие на весь день</label></div></div>
                    <div class="col-md-6"><label class="form-label" for="location">Название места</label><input class="form-control" id="location" name="location" value="{{ old('location',$event->location) }}" placeholder="Храм, зал, место сбора"></div>
                    <div class="col-md-6"><label class="form-label" for="address">Адрес</label><input class="form-control" id="address" name="address" value="{{ old('address',$event->address) }}"></div>
                    <div class="col-md-4"><label class="form-label" for="latitude">Широта</label><input class="form-control" id="latitude" name="latitude" value="{{ old('latitude',$event->latitude) }}"></div>
                    <div class="col-md-4"><label class="form-label" for="longitude">Долгота</label><input class="form-control" id="longitude" name="longitude" value="{{ old('longitude',$event->longitude) }}"></div>
                    <div class="col-md-4"><label class="form-label" for="capacity">Количество мест</label><input class="form-control" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity',$event->capacity) }}"></div>
                </div>
            </div>

            <div class="card-soft p-4">
                <h2 class="h5 mb-4">Регистрация и контакты</h2>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="registration_url">Ссылка для записи</label><input class="form-control" id="registration_url" name="registration_url" type="url" value="{{ old('registration_url',$event->registration_url) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="contact_phone">Телефон</label><input class="form-control" id="contact_phone" name="contact_phone" value="{{ old('contact_phone',$event->contact_phone) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email',$event->contact_email) }}"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-soft p-4 mb-4 position-sticky" style="top:95px">
                <h2 class="h5 mb-4">Связи и публикация</h2>
                <div class="mb-3"><label class="form-label" for="pilgrimage_object_id">Храм или объект</label><select class="form-select" id="pilgrimage_object_id" name="pilgrimage_object_id"><option value="">Не выбран</option>@foreach($objects as $object)<option value="{{ $object->id }}" data-address="{{ $object->address }}" @selected((string)old('pilgrimage_object_id',$event->pilgrimage_object_id)===(string)$object->id)>{{ $object->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label" for="pilgrimage_route_id">Маршрут</label><select class="form-select" id="pilgrimage_route_id" name="pilgrimage_route_id"><option value="">Не выбран</option>@foreach($routes as $route)<option value="{{ $route->id }}" @selected((string)old('pilgrimage_route_id',$event->pilgrimage_route_id)===(string)$route->id)>{{ $route->title }}</option>@endforeach</select></div>
                <div class="mb-4"><label class="form-label" for="trip_id">Организованная поездка</label><select class="form-select" id="trip_id" name="trip_id"><option value="">Не выбрана</option>@foreach($trips as $trip)<option value="{{ $trip->id }}" @selected((string)old('trip_id',$event->trip_id)===(string)$trip->id)>{{ optional($trip->pilgrimageRoute)->title ?: $trip->title }} — {{ optional($trip->starts_at)->format('d.m.Y H:i') }}</option>@endforeach</select></div>
                <div class="form-check form-switch mb-3"><input type="hidden" name="is_published" value="0"><input class="form-check-input" id="is_published" type="checkbox" name="is_published" value="1" @checked((bool)old('is_published',$event->is_published))><label class="form-check-label" for="is_published">Опубликовано</label></div>
                <div class="mb-4"><label class="form-label" for="published_at">Дата публикации</label><input class="form-control" id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at',optional($event->published_at)->format('Y-m-d\TH:i')) }}"></div>
                <button class="btn btn-gold w-100" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $event->exists?'Сохранить':'Создать событие' }}</button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('pilgrimage_object_id')?.addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    const address = option?.dataset.address || '';
    const addressInput = document.getElementById('address');
    const locationInput = document.getElementById('location');
    if (address && addressInput && !addressInput.value) addressInput.value = address;
    if (this.value && locationInput && !locationInput.value) locationInput.value = option.text;
});
</script>
@endpush
