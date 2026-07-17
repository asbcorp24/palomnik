@extends('admin.layouts.app')

@section('title', 'Редактирование медиаматериала')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-secondary" href="{{ route('admin.objects.edit', $media->pilgrimageObject) }}"><i class="bi bi-arrow-left me-1"></i>{{ $media->pilgrimageObject->name }}</a>
        <h1 class="page-title mt-2">Медиаматериал</h1>
        <div class="page-subtitle">Замена файла, настройка подписи, порядка и обложки.</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft p-3">
            @if($media->type === 'image' && $media->url)
                <img class="w-100 rounded-4" style="max-height:520px;object-fit:contain;background:#f7f0e6" src="{{ $media->url }}" alt="{{ $media->title }}">
            @else
                <div class="d-flex flex-column align-items-center justify-content-center rounded-4 text-secondary" style="min-height:340px;background:#f7f0e6">
                    <i class="bi {{ $media->type === 'video' ? 'bi-camera-video' : ($media->type === 'audio' ? 'bi-music-note-beamed' : 'bi-file-earmark') }} display-3"></i>
                    <div class="mt-3">{{ strtoupper($media->type) }}</div>
                    @if($media->url)<a class="btn btn-sm btn-outline-green mt-3" href="{{ $media->url }}" target="_blank" rel="noopener">Открыть файл</a>@endif
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-7">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.media.update', $media) }}">
            @csrf
            @method('PUT')
            <div class="card-soft p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="type">Тип</label>
                        <select class="form-select" id="type" name="type" required>
                            @foreach(['image' => 'Изображение', 'video' => 'Видео', 'audio' => 'Аудио', 'document' => 'Документ'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $media->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="sort_order">Порядок</label>
                        <input class="form-control" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $media->sort_order) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="replacement_file">Заменить загруженный файл</label>
                        <input class="form-control" id="replacement_file" type="file" name="replacement_file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx">
                        <div class="form-text">Поле можно оставить пустым. При замене старый файл будет удалён.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="title">Заголовок</label>
                        <input class="form-control" id="title" name="title" value="{{ old('title', $media->title) }}" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Описание</label>
                        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $media->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="external_url">Внешняя ссылка</label>
                        <input class="form-control" id="external_url" type="url" name="external_url" value="{{ old('external_url', $media->external_url) }}">
                        @if($media->path)<div class="form-text">Для загруженного файла внешний URL можно оставить пустым.</div>@endif
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_cover" value="0">
                            <input class="form-check-input" id="is_cover" type="checkbox" name="is_cover" value="1" @checked((bool)old('is_cover', $media->is_cover)) @disabled($media->type !== 'image')>
                            <label class="form-check-label" for="is_cover">Использовать как обложку объекта</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-gold px-4" type="submit"><i class="bi bi-check-lg me-1"></i>Сохранить</button>
                <a class="btn btn-light px-4" href="{{ route('admin.objects.edit', $media->pilgrimageObject) }}">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection
