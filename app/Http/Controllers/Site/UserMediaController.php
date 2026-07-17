<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\UserMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserMediaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:30720'],
            'pilgrimage_object_id' => ['nullable', 'integer', 'exists:pilgrimage_objects,id'],
            'blog_post_id' => ['nullable', 'integer', 'exists:blog_posts,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! empty($data['blog_post_id'])) {
            $post = BlogPost::query()->findOrFail($data['blog_post_id']);
            abort_unless($post->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        }

        $file = $request->file('file');
        $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $path = $file->store('community/'.now()->format('Y/m'), 'public');

        $request->user()->media()->create([
            'pilgrimage_object_id' => $data['pilgrimage_object_id'] ?? null,
            'blog_post_id' => $data['blog_post_id'] ?? null,
            'type' => $type,
            'path' => $path,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Файл загружен и отправлен на модерацию.');
    }

    public function destroy(Request $request, UserMedia $media): RedirectResponse
    {
        abort_unless($media->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Медиаматериал удалён.');
    }
}
