<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function create(): View
    {
        return view('site.community.form', ['post' => new BlogPost()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $status = $request->input('action') === 'submit' ? 'pending' : 'draft';

        $post = $request->user()->blogPosts()->create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'status' => $status,
        ]);

        return redirect()
            ->route('community.posts.edit', $post)
            ->with('success', $status === 'pending'
                ? 'Публикация отправлена на модерацию.'
                : 'Черновик сохранён.');
    }

    public function edit(Request $request, BlogPost $post): View
    {
        $this->authorizeOwner($request, $post);
        $post->load('media');

        return view('site.community.form', compact('post'));
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $this->authorizeOwner($request, $post);
        $data = $this->validated($request);
        $status = $request->input('action') === 'submit' ? 'pending' : 'draft';

        $post->update([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], $post->id),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'status' => $status,
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
        ]);

        return back()->with('success', $status === 'pending'
            ? 'Публикация отправлена на модерацию.'
            : 'Черновик сохранён.');
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $this->authorizeOwner($request, $post);
        $post->delete();

        return redirect()->route('profile.activity')->with('success', 'Публикация удалена.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'min:50'],
        ]);
    }

    private function authorizeOwner(Request $request, BlogPost $post): void
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'post-'.now()->format('Ymd-His');
        }

        $candidate = $base;
        $counter = 2;

        while (BlogPost::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }
}
