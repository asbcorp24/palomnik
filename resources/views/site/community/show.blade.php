@extends('site.layouts.app')

@section('title', $post->title.' — Московский паломник')
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 160))

@section('content')
<section class="page-hero">
    <div class="container" style="max-width:900px">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('community.index') }}">Сообщество</a></li><li class="breadcrumb-item active">{{ $post->title }}</li></ol></nav>
        <div class="section-kicker mb-2">Путевая заметка</div>
        <h1 class="section-title mb-3">{{ $post->title }}</h1>
        <div class="text-secondary">{{ optional($post->user)->name }} · {{ ($post->published_at ?: $post->created_at)->format('d.m.Y') }}</div>
    </div>
</section>

<section class="section-space pt-5">
    <article class="container" style="max-width:900px">
        @php($cover = $post->media->firstWhere('type', 'image'))
        @if($cover)<img class="post-cover mb-5" src="{{ $cover->url }}" alt="{{ $post->title }}">@endif
        @if($post->excerpt)<p class="fs-5 lh-lg mb-5">{{ $post->excerpt }}</p>@endif
        <div class="lh-lg fs-6">{!! nl2br(e($post->body)) !!}</div>

        @if($post->media->count() > 1)
            <div class="row g-3 mt-5">
                @foreach($post->media->skip($cover ? 1 : 0) as $media)
                    <div class="col-md-6">@if($media->type === 'video')<video class="w-100 rounded-4" controls src="{{ $media->url }}"></video>@else<img class="gallery-image" src="{{ $media->url }}" alt="{{ $media->title ?: $post->title }}">@endif</div>
                @endforeach
            </div>
        @endif

        @auth
            @if(auth()->id() === $post->user_id || auth()->user()->isAdmin())
                <div class="d-flex gap-2 mt-5 pt-4 border-top"><a class="btn btn-outline-pm" href="{{ route('community.posts.edit', $post) }}">Редактировать</a></div>
            @endif
        @endauth
    </article>
</section>
@endsection
