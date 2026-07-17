<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectMedia;
use App\Models\PilgrimageObject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ObjectMediaController extends Controller
{
    public function store(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,mp3,wav,m4a,mp4,mov,avi,pdf,doc,docx'],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'external_type' => ['nullable', 'in:image,video,audio,document'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $files = $request->file('files', []);

        if (empty($files) && empty($data['external_url'])) {
            return back()->with('error', 'Выберите файл или укажите внешнюю ссылку.');
        }

        $sortOrder = (int) $object->media()->max('sort_order');
        $hasCover = $object->media()->where('is_cover', true)->exists();

        foreach ($files as $file) {
            $mime = (string) $file->getMimeType();
            $type = Str::startsWith($mime, 'image/') ? 'image'
                : (Str::startsWith($mime, 'video/') ? 'video'
                    : (Str::startsWith($mime, 'audio/') ? 'audio' : 'document'));

            $object->media()->create([
                'type' => $type,
                'path' => $file->store('objects/'.$object->id, 'public'),
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'sort_order' => ++$sortOrder,
                'is_cover' => ! $hasCover && $type === 'image',
            ]);

            if (! $hasCover && $type === 'image') {
                $hasCover = true;
            }
        }

        if (! empty($data['external_url'])) {
            $object->media()->create([
                'type' => $data['external_type'] ?? 'image',
                'external_url' => $data['external_url'],
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => ++$sortOrder,
                'is_cover' => ! $hasCover && ($data['external_type'] ?? 'image') === 'image',
            ]);
        }

        return back()->with('success', 'Медиаматериалы добавлены.');
    }

    public function edit(ObjectMedia $media): View
    {
        $media->load('pilgrimageObject');

        return view('admin.media.edit', compact('media'));
    }

    public function update(Request $request, ObjectMedia $media): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:image,video,audio,document'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'external_url' => ['nullable', 'url', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_cover' => ['nullable', 'boolean'],
        ]);

        $makeCover = $request->boolean('is_cover') && $data['type'] === 'image';

        DB::transaction(function () use ($media, $data, $makeCover) {
            if ($makeCover) {
                ObjectMedia::query()
                    ->where('pilgrimage_object_id', $media->pilgrimage_object_id)
                    ->whereKeyNot($media->id)
                    ->update(['is_cover' => false]);
            }

            $data['is_cover'] = $makeCover;
            $media->update($data);
        });

        return redirect()
            ->route('admin.objects.edit', $media->pilgrimageObject)
            ->with('success', 'Медиаматериал обновлён.');
    }

    public function destroy(ObjectMedia $media): RedirectResponse
    {
        $object = $media->pilgrimageObject;
        $wasCover = $media->is_cover;

        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();

        if ($wasCover) {
            $nextCover = $object->media()->where('type', 'image')->orderBy('sort_order')->first();
            if ($nextCover) {
                $nextCover->update(['is_cover' => true]);
            }
        }

        return redirect()
            ->route('admin.objects.edit', $object)
            ->with('success', 'Медиаматериал удалён.');
    }
}
