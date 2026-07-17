@extends('site.profile.layout')

@section('title', 'Моя активность — Московский паломник')
@section('profile_title', 'Моя активность')
@section('profile_subtitle', 'Посещения, отзывы, публикации и загруженные материалы.')

@section('profile_content')
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-pm-gold" href="{{ route('community.posts.create') }}"><i class="bi bi-pencil-square me-2"></i>Написать заметку</a>
    <a class="btn btn-outline-pm" href="{{ route('objects.index') }}"><i class="bi bi-camera me-2"></i>Добавить фото к объекту</a>
</div>

<div class="accordion d-grid gap-3" id="activityAccordion">
    <div class="profile-card p-0 overflow-hidden">
        <button class="btn w-100 text-start p-4 d-flex justify-content-between" data-bs-toggle="collapse" data-bs-target="#visitsBlock"><span><i class="bi bi-geo-fill me-2"></i><strong>Посещения</strong> <span class="text-secondary">({{ $visits->count() }})</span></span><i class="bi bi-chevron-down"></i></button>
        <div id="visitsBlock" class="collapse show" data-bs-parent="#activityAccordion">
            <div class="border-top">
                @forelse($visits as $visit)
                    <div class="p-3 border-bottom d-flex justify-content-between gap-3"><div><a class="fw-semibold text-decoration-none" href="{{ $visit->pilgrimageObject ? route('objects.show', $visit->pilgrimageObject) : '#' }}">{{ optional($visit->pilgrimageObject)->name ?: 'Объект удалён' }}</a><div class="small text-secondary">{{ $visit->visited_at->format('d.m.Y H:i') }}</div></div><span class="status-badge {{ $visit->status === 'verified' ? 'status-verified' : ($visit->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">{{ $visit->status }}</span></div>
                @empty<div class="empty-state py-4">Посещений пока нет.</div>@endforelse
            </div>
        </div>
    </div>

    <div class="profile-card p-0 overflow-hidden">
        <button class="btn w-100 text-start p-4 d-flex justify-content-between collapsed" data-bs-toggle="collapse" data-bs-target="#reviewsBlock"><span><i class="bi bi-chat-square-text me-2"></i><strong>Отзывы</strong> <span class="text-secondary">({{ $reviews->count() }})</span></span><i class="bi bi-chevron-down"></i></button>
        <div id="reviewsBlock" class="collapse" data-bs-parent="#activityAccordion"><div class="border-top">
            @forelse($reviews as $review)
                <div class="p-3 border-bottom"><div class="d-flex justify-content-between gap-3"><div><a class="fw-semibold text-decoration-none" href="{{ $review->pilgrimageObject ? route('objects.show', $review->pilgrimageObject) : '#' }}">{{ optional($review->pilgrimageObject)->name ?: 'Объект удалён' }}</a><div class="review-stars small">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div></div><span class="status-badge {{ $review->status === 'published' ? 'status-published' : ($review->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">{{ $review->status }}</span></div><p class="small text-secondary mt-2 mb-0">{{ $review->body }}</p></div>
            @empty<div class="empty-state py-4">Отзывов пока нет.</div>@endforelse
        </div></div>
    </div>

    <div class="profile-card p-0 overflow-hidden">
        <button class="btn w-100 text-start p-4 d-flex justify-content-between collapsed" data-bs-toggle="collapse" data-bs-target="#postsBlock"><span><i class="bi bi-journal-richtext me-2"></i><strong>Путевые заметки</strong> <span class="text-secondary">({{ $posts->count() }})</span></span><i class="bi bi-chevron-down"></i></button>
        <div id="postsBlock" class="collapse" data-bs-parent="#activityAccordion"><div class="border-top">
            @forelse($posts as $post)
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-3"><div><div class="fw-semibold">{{ $post->title }}</div><div class="small text-secondary">{{ $post->created_at->format('d.m.Y') }}</div></div><div class="d-flex align-items-center gap-2"><span class="status-badge {{ $post->status === 'published' ? 'status-published' : ($post->status === 'rejected' ? 'status-rejected' : ($post->status === 'draft' ? 'status-draft' : 'status-pending')) }}">{{ $post->status }}</span><a class="btn btn-sm btn-light" href="{{ route('community.posts.edit', $post) }}"><i class="bi bi-pencil"></i></a></div></div>
            @empty<div class="empty-state py-4">Публикаций пока нет.</div>@endforelse
        </div></div>
    </div>

    <div class="profile-card p-0 overflow-hidden">
        <button class="btn w-100 text-start p-4 d-flex justify-content-between collapsed" data-bs-toggle="collapse" data-bs-target="#mediaBlock"><span><i class="bi bi-camera me-2"></i><strong>Фото и видео</strong> <span class="text-secondary">({{ $mediaItems->count() }})</span></span><i class="bi bi-chevron-down"></i></button>
        <div id="mediaBlock" class="collapse" data-bs-parent="#activityAccordion"><div class="p-3 border-top"><div class="row g-3">
            @forelse($mediaItems as $media)
                <div class="col-6 col-md-4"><div class="position-relative">@if($media->type === 'video')<video class="community-media" controls src="{{ $media->url }}"></video>@else<img class="community-media" src="{{ $media->url }}" alt="{{ $media->title }}">@endif<span class="status-badge position-absolute top-0 end-0 m-2 {{ $media->status === 'published' ? 'status-published' : ($media->status === 'rejected' ? 'status-rejected' : 'status-pending') }}">{{ $media->status }}</span></div></div>
            @empty<div class="col-12"><div class="empty-state py-4">Медиаматериалов пока нет.</div></div>@endforelse
        </div></div></div>
    </div>
</div>
@endsection
