@extends('site.layouts.app')

@section('title', 'Сообщество паломников — Московский паломник')

@section('content')
<section class="page-hero">
    <div class="container">
        <div class="row align-items-end g-4">
            <div class="col-lg-8"><div class="section-kicker mb-2">Люди и истории</div><h1 class="section-title mb-3">Сообщество паломников</h1><p class="section-lead mb-0">Отзывы о святых местах, фотографии и путевые заметки участников.</p></div>
            <div class="col-lg-4 text-lg-end">@auth<a class="btn btn-pm-gold" href="{{ route('community.posts.create') }}"><i class="bi bi-pencil-square me-2"></i>Написать заметку</a>@else<a class="btn btn-pm-gold" href="{{ route('register') }}">Присоединиться</a>@endauth</div>
        </div>
    </div>
</section>

<section class="section-space">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end gap-3 mb-4"><div><div class="section-kicker mb-2">Блог</div><h2 class="section-title h2 mb-0">Путевые заметки</h2></div></div>
        <div class="row g-4">
            @forelse($posts as $post)
                @php($cover = $post->media->firstWhere('type', 'image'))
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm">
                        @if($cover)<img class="object-cover" src="{{ $cover->url }}" alt="{{ $post->title }}">@else<div class="object-placeholder"><i class="bi bi-journal-richtext"></i></div>@endif
                        <div class="p-4">
                            <div class="small text-secondary mb-2">{{ $post->published_at?->format('d.m.Y') }} · {{ optional($post->user)->name }}</div>
                            <h3 class="object-title mb-3"><a class="text-decoration-none" href="{{ route('community.show', $post) }}">{{ $post->title }}</a></h3>
                            <p class="small text-secondary mb-3">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 150) }}</p>
                            <a class="fw-semibold text-decoration-none" style="color:var(--pm-green)" href="{{ route('community.show', $post) }}">Читать <i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="filter-card empty-state">Опубликованных заметок пока нет.</div></div>
            @endforelse
        </div>
        @if($posts->hasPages())<div class="mt-5">{{ $posts->links() }}</div>@endif
    </div>
</section>

@if($mediaItems->isNotEmpty())
<section class="section-space section-soft">
    <div class="container">
        <div class="section-kicker mb-2">Впечатления</div><h2 class="section-title h2 mb-4">Фотографии участников</h2>
        <div class="row g-3">
            @foreach($mediaItems as $media)
                <div class="col-6 col-md-3"><div class="position-relative"><img class="community-media" src="{{ $media->url }}" alt="{{ $media->title }}"><div class="position-absolute bottom-0 start-0 end-0 p-3 text-white" style="background:linear-gradient(transparent,rgba(0,0,0,.72));border-radius:0 0 16px 16px"><div class="small fw-semibold">{{ optional($media->pilgrimageObject)->name ?: $media->title }}</div><div class="small opacity-75">{{ optional($media->user)->name }}</div></div></div></div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($reviews->isNotEmpty())
<section class="section-space">
    <div class="container">
        <div class="section-kicker mb-2">Отзывы</div><h2 class="section-title h2 mb-4">Что говорят паломники</h2>
        <div class="row g-4">
            @foreach($reviews as $review)
                <div class="col-md-6 col-xl-4"><article class="review-card h-100"><div class="review-stars mb-3">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div><p class="mb-4">{{ \Illuminate\Support\Str::limit($review->body, 260) }}</p><div class="small text-secondary"><strong class="text-dark">{{ optional($review->user)->name }}</strong><br>@if($review->pilgrimageObject)<a href="{{ route('objects.show', $review->pilgrimageObject) }}">{{ $review->pilgrimageObject->name }}</a>@endif</div></article></div>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
