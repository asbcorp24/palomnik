@extends('admin.layouts.app')

@section('title', ($item->exists ? 'Редактирование: ' : 'Добавление: ').$config['single'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.directories.index', $resource) }}">
            <i class="bi bi-arrow-left me-1"></i>{{ $config['title'] }}
        </a>
        <h1 class="page-title mt-2">{{ $item->exists ? 'Редактирование' : 'Новая запись' }}</h1>
        <div class="page-subtitle">{{ $config['single'] }}</div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.directories.update', [$resource, $item->id]) : route('admin.directories.store', $resource) }}">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="card-soft p-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <label class="form-label required" for="name">Название</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $item->name) }}" maxlength="255" required>
            </div>
            <div class="col-lg-4">
                <label class="form-label" for="slug">Slug</label>
                <input class="form-control" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" maxlength="255" placeholder="создастся автоматически">
                <div class="form-text">Латинский идентификатор для API и ссылок.</div>
            </div>

            @if($resource === 'deaneries')
                <div class="col-12">
                    <label class="form-label required" for="vicariate_id">Викариатство</label>
                    <select class="form-select" id="vicariate_id" name="vicariate_id" required>
                        <option value="">Выберите викариатство</option>
                        @foreach($vicariates as $vicariate)
                            <option value="{{ $vicariate->id }}" @selected((string) old('vicariate_id', $item->vicariate_id) === (string) $vicariate->id)>{{ $vicariate->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($resource === 'sanctities')
                <div class="col-lg-6">
                    <label class="form-label" for="type">Тип святыни</label>
                    <input class="form-control" id="type" name="type" value="{{ old('type', $item->type) }}" maxlength="64" placeholder="икона, мощи...">
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="image">Фотография святыни</label>
                    <input class="form-control" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG, PNG или WebP, до 5 МБ.</div>
                </div>
                @if($item->image_url)
                    <div class="col-12">
                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="rounded-4 border" style="width:240px;height:160px;object-fit:cover">
                        <div class="form-check mt-2">
                            <input class="form-check-input" id="remove_image" type="checkbox" name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Удалить текущую фотографию</label>
                        </div>
                    </div>
                @endif
            @endif

            @if($resource === 'object-types')
                <div class="col-md-4">
                    <label class="form-label" for="marker_color">Цвет маркера</label>
                    <input class="form-control form-control-color w-100" id="marker_color" type="color" name="marker_color" value="{{ old('marker_color', $item->marker_color ?: '#b08a3e') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="icon">Иконка</label>
                    <input class="form-control" id="icon" name="icon" value="{{ old('icon', $item->icon) }}" placeholder="church">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sort_order">Порядок</label>
                    <input class="form-control" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch border rounded-4 p-3 ps-5 h-100">
                        <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item->exists ? $item->is_active : true))>
                        <label class="form-check-label fw-semibold" for="is_active">Тип активен</label>
                        <div class="form-text">Неактивный тип нельзя использовать для новых публичных объектов. Старые записи сохраняются.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch border rounded-4 p-3 ps-5 h-100">
                        <input class="form-check-input" id="is_public" type="checkbox" name="is_public" value="1" @checked((bool) old('is_public', $item->exists ? $item->is_public : true))>
                        <label class="form-check-label fw-semibold" for="is_public">Показывать на сайте и в API</label>
                        <div class="form-text">Отключите для внутренних и архивных типов. Связанные объекты останутся доступны администраторам.</div>
                    </div>
                </div>
            @else
                <div class="col-12">
                    <label class="form-label" for="description">Описание</label>
                    <textarea class="form-control" id="description" name="description" rows="6">{{ old('description', $item->description) }}</textarea>
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить</button>
        <a class="btn btn-light px-4" href="{{ route('admin.directories.index', $resource) }}">Отмена</a>
    </div>
</form>
@endsection
