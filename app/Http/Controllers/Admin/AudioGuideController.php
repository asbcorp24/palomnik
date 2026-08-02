<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AudioGuide;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AudioGuideController extends Controller
{
    public function updateObject(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $this->save($request, $object, 'objects/'.$object->id);

        return back()->with('success', 'Аудиогид объекта сохранён.');
    }

    public function destroyObject(PilgrimageObject $object): RedirectResponse
    {
        $this->destroyGuide($object);

        return back()->with('success', 'Аудиогид объекта удалён.');
    }

    public function updateRoute(Request $request, PilgrimageRoute $pilgrimageRoute): RedirectResponse
    {
        $this->save($request, $pilgrimageRoute, 'routes/'.$pilgrimageRoute->id);

        return back()->with('success', 'Аудиогид маршрута сохранён.');
    }

    public function destroyRoute(PilgrimageRoute $pilgrimageRoute): RedirectResponse
    {
        $this->destroyGuide($pilgrimageRoute);

        return back()->with('success', 'Аудиогид маршрута удалён.');
    }

    private function save(Request $request, Model $guideable, string $directory): AudioGuide
    {
        $existing = $guideable->audioGuide()->first();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'transcript' => ['nullable', 'string', 'max:100000'],
            'audio_file' => [
                Rule::requiredIf($existing === null),
                'nullable',
                'file',
                'max:102400',
                'mimes:mp3,m4a,aac,ogg,oga,wav,webm',
            ],
        ]);

        $file = $request->file('audio_file');

        if (! $existing && ! $file) {
            throw ValidationException::withMessages([
                'audio_file' => 'Выберите аудиофайл.',
            ]);
        }

        $guide = $existing ?: $guideable->audioGuide()->make();
        $oldPath = $guide->path;

        $guide->title = trim((string) ($data['title'] ?? '')) ?: null;
        $guide->transcript = trim((string) ($data['transcript'] ?? '')) ?: null;

        if ($file) {
            $guide->path = $file->store('audio-guides/'.$directory, 'public');
            $guide->original_name = $file->getClientOriginalName();
            $guide->mime_type = $file->getMimeType();
            $guide->size = $file->getSize();
        }

        $guide->save();

        if ($file && $oldPath && $oldPath !== $guide->path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $guide;
    }

    private function destroyGuide(Model $guideable): void
    {
        $guide = $guideable->audioGuide()->first();

        if ($guide) {
            $guide->delete();
        }
    }
}
