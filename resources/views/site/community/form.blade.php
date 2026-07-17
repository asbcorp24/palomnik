@extends('site.profile.layout')

@section('title', ($post->exists ? 'Редактировать заметку' : 'Новая заметка').' — Московский паломник')
@section('profile_title', $post->exists ? 'Редактировать заметку' : 'Новая путевая заметка')
@section('profile_subtitle', 'Черновик виден только вам. После отправки публикация проходит модерацию.')

@section('profile_content')
<form class="profile-card mb-4" method="POST" action="{{ $post->exists ? route('community.posts.update', $post) : route('community.posts.store') }}">
    @csrf
    @if($post->exists)@method('PUT')@endif

    <div class="mb-3">
        <label class="form-label" for="title">Заголовок</label>
        <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $post->title) }}" required maxlength="255">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="excerpt">Краткое описание</label>
        <textarea class="form-control @error('excerpt') is-invalid @enderror" id="excerpt" name="excerpt" rows="3" maxlength="1000">{{ old('excerpt', $post->excerpt) }}</textarea>
        @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
        <label class="form-label" for="body">Текст заметки</label>
        <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="16" required>{{ old('body', $post->body) }}</textarea>
        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-light px-4" type="submit" name="action" value="draft"><i class="bi bi-save me-2"></i>Сохранить черновик</button>
        <button class="btn btn-pm-gold px-4" type="submit" name="action" value="submit"><i class="bi bi-send me-2"></i>Отправить на модерацию</button>
        @if($post->exists)
            <a class="btn btn-outline-pm" href="{{ route('community.show', $post) }}">Предпросмотр</a>
        @endif
    </div>
</form>

@if($post->exists)
<div class="profile-card mb-4">
    <div class="section-kicker mb-2">Медиа</div>
    <h2 class="h4 mb-4">Фотографии и видео</h2>
    <form method="POST" action="{{ route('community.media.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="blog_post_id" value="{{ $post->id }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-6"><label class="form-label" for="file">Файл</label><input class="form-control" id="file" name="file" type="file" accept="image/*,video/*" required></div>
            <div class="col-md-4"><label class="form-label" for="media_title">Подпись</label><input class="form-control" id="media_title" name="title"></div>
            <div class="col-md-2"><button class="btn btn-pm-green w-100" type="submit">Загрузить</button></div>
        </div>
        <div class="form-text mt-2">Фотографии и видео публикуются после модерации.</div>
    </form>

    @if($post->media->isNotEmpty())
        <div class="row g-3 mt-3">
            @foreach($post->media as $media)
                <div class="col-6 col-md-4">
                    <div class="position-relative">
                        @if($media->type === 'video')<video class="community-media" controls src="{{ $media->url }}"></video>@else<img class="community-media" src="{{ $media->url }}" alt="{{ $media->title }}">@endif
                        <span class="status-badge position-absolute top-0 start-0 m-2 {{ $media->status === 'published' ? 'status-published' : ($media->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">{{ $media->status }}</span>
                        <form class="position-absolute top-0 end-0 m-2" method="POST" action="{{ route('community.media.destroy', $media) }}" onsubmit="return confirm('Удалить файл?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<form method="POST" action="{{ route('community.posts.destroy', $post) }}" onsubmit="return confirm('Удалить публикацию полностью?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">Удалить публикацию</button></form>
@endif
@endsection
