<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileContentController extends Controller
{
    public function posts(Request $request): JsonResponse
    {
        $items = $request->user()->blogPosts()
            ->with(['media' => fn ($query) => $query->latest()])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn (BlogPost $post) => $this->postData($post))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'min:50', 'max:100000'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $post = BlogPost::query()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'excerpt' => $data['excerpt'] ?? Str::limit(strip_tags($data['body']), 300),
            'body' => $data['body'],
            'status' => $request->boolean('publish') ? 'pending' : 'draft',
        ]);

        return response()->json(['data' => $this->postData($post)], 201);
    }

    public function updatePost(Request $request, BlogPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'min:50', 'max:100000'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $post->update([
            'title' => $data['title'],
            'slug' => $post->title === $data['title'] ? $post->slug : $this->uniqueSlug($data['title'], $post->id),
            'excerpt' => $data['excerpt'] ?? Str::limit(strip_tags($data['body']), 300),
            'body' => $data['body'],
            'status' => $request->boolean('publish') ? 'pending' : 'draft',
            'moderated_by' => null,
            'moderated_at' => null,
            'published_at' => null,
        ]);

        return response()->json(['data' => $this->postData($post->fresh())]);
    }

    public function destroyPost(Request $request, BlogPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $post->delete();

        return response()->json(['deleted' => true]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'pilgrim-note';
        $slug = $base;
        $index = 2;

        while (BlogPost::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function postData(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'status' => $post->status,
            'moderated_at' => optional($post->moderated_at)->toIso8601String(),
            'published_at' => optional($post->published_at)->toIso8601String(),
            'created_at' => optional($post->created_at)->toIso8601String(),
            'media' => $post->relationLoaded('media')
                ? $post->media->map(fn ($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => $media->url,
                    'title' => $media->title,
                    'status' => $media->status,
                ])->values()
                : [],
        ];
    }
}
