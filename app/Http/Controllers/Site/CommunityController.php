<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Review;
use App\Models\UserMedia;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()
            ->where('status', 'published')
            ->with(['user', 'media' => fn ($query) => $query->where('status', 'published')])
            ->orderByDesc('published_at')
            ->paginate(9);

        $reviews = Review::query()
            ->where('status', 'published')
            ->with(['user', 'pilgrimageObject'])
            ->latest()
            ->limit(6)
            ->get();

        $mediaItems = UserMedia::query()
            ->where('status', 'published')
            ->where('type', 'image')
            ->with(['user', 'pilgrimageObject'])
            ->latest()
            ->limit(8)
            ->get();

        return view('site.community.index', compact('posts', 'reviews', 'mediaItems'));
    }

    public function show(BlogPost $post): View
    {
        $canSee = $post->status === 'published'
            || (auth()->check() && (auth()->id() === $post->user_id || auth()->user()->isAdmin()));

        abort_unless($canSee, 404);

        $post->load(['user', 'media' => fn ($query) => $query->where('status', 'published')]);

        return view('site.community.show', compact('post'));
    }
}
