@extends('admin.layouts.app')

@section('title', $object->exists ? 'Редактирование объекта' : 'Новый объект')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.objects.index') }}"><i class="bi bi-arrow-left me-1"></i>Храмы и объекты</a>
        <h1 class="page-title mt-2">{{ $object->exists ? 'Редактирование объекта' : 'Новый объект' }}</h1>
        @if($object->exists)<div class="page-subtitle">{{ $object->name }}</div>@endif
    </div>
    @if($object->exists)
        <a class="btn btn-outline-green" href="{{ route('admin.objects.show', $object) }}"><i class="bi bi-eye me-1"></i>Просмотр</a>
    @endif
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $object->exists ? route('admin.objects.update', $object) : route('admin.objects.store') }}">
    @csrf
    @if($object->exists) @method('PUT') @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Основная информация</h2>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label required" for="object_type_id">Тип объекта</label>
                        <select class="form-select" id="object_type_id" name="object_type_id" required>
                            <option value="">Выберите тип</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @selected((string)old('object_type_id', $object->object_type_id) === (string)$type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label required" for="name">Название</label>
                        <input class="form-control" id="name" name="name" value="{{ old('name', $object->name) }}" maxlength="255" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="slug">Slug</label>
                        <input class="form-control" id="slug" name="slug" value="{{ old('slug', $object->slug) }}" maxlength="255" placeholder="создастся автоматически">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="short_description">Краткое описание</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="3" maxlength="2000">{{ old('short_description', $object->short_description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Полное описание</label>
                        <textarea class="form-control" id="description" name="description" rows="8">{{ old('description', $object->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="history">История</label>
                        <textarea class="form-control" id="history" name="history" rows="8">{{ old('history', $object->history) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Расположение</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="address">Адрес</label>
                        <input class="form-control" id="address" name="address" value="{{ old('address', $object->address) }}" maxlength="500" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="latitude">Широта</label>
                        <input class="form-control" id="latitude" type="number" step="0.0000001" min="-90" max="90" name="latitude" value="{{ old('latitude', $object->latitude) }}" placeholder="55.7558000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="longitude">Долгота</label>
                        <input class="form-control" id="longitude" type="number" step="0.0000001" min="-180" max="180" name="longitude" value="{{ old('longitude', $object->longitude) }}" placeholder="37.6176000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vicariate_id">Викариатство</label>
                        <select class="form-select" id="vicariate_id" name="vicariate_id">
                            <option value="">Не указано</option>
                            @foreach($vicariates as $vicariate)
                                <option value="{{ $vicariate->id }}" @selected((string)old('vicariate_id', $object->vicariate_id) === (string)$vicariate->id)>{{ $vicariate->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="deanery_id">Благочиние</label>
                        <select class="form-select" id="deanery_id" name="deanery_id">
                            <option value="">Не указано</option>
                            @foreach($deaneries as $deanery)
                                <option value="{{ $deanery->id }}" data-vicariate="{{ $deanery->vicariate_id }}" @selected((string)old('deanery_id', $object->deanery_id) === (string)$deanery->id)>{{ $deanery->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4 mb-4">
                <h2 class="h5 mb-4">Контакты и посещение</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Телефон</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $object->phone) }}" maxlength="64">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $object->email) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="website">Сайт</label>
                        <input class="form-control" id="website" type="url" name="website" value="{{ old('website', $object->website) }}" placeholder="https://">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="schedule_text">Режим работы и расписание богослужений</label>
                        <textarea class="form-control" id="schedule_text" name="schedule_text" rows="5">{{ old('schedule_text', $object->schedule_text) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="parking_info">Парковка</label>
                        <textarea class="form-control" id="parking_info" name="parking_info" rows="4">{{ old('parking_info', $object->parking_info) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="accessibility_info">Доступность для маломобильных</label>
                        <textarea class="form-control" id="accessibility_info" name="accessibility_info" rows="4">{{ old('accessibility_info', $object->accessibility_info) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card-soft p-4">
                <h2 class="h5 mb-3">Медиаматериалы</h2>
                <label class="form-label" for="media_files">Загрузить файлы</label>
                <input class="form-control" id="media_files" type="file" name="media_files[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                <div class="form-text">До 20 файлов за раз, максимальный размер одного файла — 50 МБ. Первая фотография станет обложкой.</div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-soft p-4 mb-4 position-sticky" style="top:96px">
                <h2 class="h5 mb-4">Публикация</h2>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_published" value="0">
                    <input class="form-check-input" id="is_published" type="checkbox" name="is_published" value="1" @checked((bool)old('is_published', $object->is_published))>
                    <label class="form-check-label fw-semibold" for="is_published">Опубликовать объект</label>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="published_at">Дата публикации</label>
                    <input class="form-control" id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at', optional($object->published_at)->format('Y-m-d\TH:i')) }}">
                </div>

                <h3 class="h6 mb-3">Святыни</h3>
                <div class="border rounded-3 p-2 mb-4" style="max-height:320px;overflow:auto">
                    @forelse($sanctities as $sanctity)
                        <label class="d-flex gap-2 align-items-start p-2 rounded">
                            <input class="form-check-input mt-1" type="checkbox" name="sanctity_ids[]" value="{{ $sanctity->id }}" @checked(in_array($sanctity->id, old('sanctity_ids', $selectedSanctities)))>
                            <span>
                                <span class="d-block">{{ $sanctity->name }}</span>
                                @if($sanctity->type)<span class="small text-secondary">{{ $sanctity->type }}</span>@endif
                            </span>
                        </label>
                    @empty
                        <div class="small text-secondary p-2">Сначала добавьте святыни в справочнике.</div>
                    @endforelse
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-gold" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить объект</button>
                    <a class="btn btn-light" href="{{ route('admin.objects.index') }}">Отмена</a>
                </div>
            </div>
        </div>
    </div>
</form>

@if($object->exists)
    <div class="card-soft p-4 mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="h5 mb-1">Галерея и файлы</h2>
                <div class="small text-secondary">Редактирование, выбор обложки и удаление материалов.</div>
            </div>
            <button class="btn btn-outline-green" type="button" data-bs-toggle="collapse" data-bs-target="#addMedia"><i class="bi bi-plus-lg me-1"></i>Добавить медиа</button>
        </div>

        <div class="collapse mb-4" id="addMedia">
            <form class="border rounded-4 p-3 bg-light" method="POST" enctype="multipart/form-data" action="{{ route('admin.objects.media.store', $object) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-7">
                        <label class="form-label">Файлы</label>
                        <input class="form-control" type="file" name="files[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Внешняя ссылка</label>
                        <input class="form-control" type="url" name="external_url" placeholder="https://">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Тип внешней ссылки</label>
                        <select class="form-select" name="external_type">
                            <option value="image">Изображение</option>
                            <option value="video">Видео</option>
                            <option value="audio">Аудио</option>
                            <option value="document">Документ</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Заголовок ссылки</label>
                        <input class="form-control" name="title">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-gold w-100" type="submit">Добавить</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row g-3">
            @forelse($object->media as $media)
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="border rounded-4 p-2 h-100 bg-white">
                        @if($media->type === 'image' && $media->url)
                            <img class="media-preview" src="{{ $media->url }}" alt="{{ $media->title }}">
                        @else
                            <div class="media-preview d-flex flex-column align-items-center justify-content-center text-secondary">
                                <i class="bi {{ $media->type === 'video' ? 'bi-camera-video' : ($media->type === 'audio' ? 'bi-music-note-beamed' : 'bi-file-earmark') }} fs-1"></i>
                                <span class="small mt-2">{{ strtoupper($media->type) }}</span>
                            </div>
                        @endif
                        <div class="p-2">
                            <div class="fw-semibold text-truncate">{{ $media->title ?: 'Без названия' }}</div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    @if($media->is_cover)<span class="badge badge-published">Обложка</span>@endif
                                </div>
                                <div class="text-nowrap">
                                    <a class="btn btn-sm btn-light" href="{{ route('admin.media.edit', $media) }}"><i class="bi bi-pencil"></i></a>
                                    <form class="d-inline" method="POST" action="{{ route('admin.media.destroy', $media) }}" onsubmit="return confirm('Удалить медиаматериал?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-secondary py-4">Медиаматериалы пока не добавлены.</div>
            @endforelse
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const vicariate = document.getElementById('vicariate_id');
    const deanery = document.getElementById('deanery_id');
    if (!vicariate || !deanery) return;

    function filterDeaneries() {
        const selected = vicariate.value;
        Array.from(deanery.options).forEach(function (option) {
            if (!option.value) return;
            const visible = !selected || option.dataset.vicariate === selected;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (deanery.selectedOptions[0] && deanery.selectedOptions[0].disabled) deanery.value = '';
    }

    vicariate.addEventListener('change', filterDeaneries);
    filterDeaneries();
})();
</script>
@endpush
