<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use App\Models\Vicariate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PilgrimageObjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'exists:object_types,id'],
            'status' => ['nullable', 'in:published,draft'],
        ]);

        $objects = PilgrimageObject::query()
            ->with(['objectType', 'vicariate', 'deanery', 'coverMedia'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, int $type) => $query->where('object_type_id', $type))
            ->when(($filters['status'] ?? null) === 'published', fn (Builder $query) => $query->where('is_published', true))
            ->when(($filters['status'] ?? null) === 'draft', fn (Builder $query) => $query->where('is_published', false))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.objects.index', [
            'objects' => $objects,
            'types' => ObjectType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new PilgrimageObject());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $sanctityIds = $data['sanctity_ids'] ?? [];
        $files = $request->file('media_files', []);
        unset($data['sanctity_ids'], $data['media_files']);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published']
            ? ($data['published_at'] ?? now())
            : null;

        $object = DB::transaction(function () use ($data, $sanctityIds) {
            $object = PilgrimageObject::query()->create($data);
            $object->sanctities()->sync($sanctityIds);

            return $object;
        });

        $this->storeUploadedMedia($object, $files);

        return redirect()
            ->route('admin.objects.edit', $object)
            ->with('success', 'Паломнический объект создан.');
    }

    public function show(PilgrimageObject $object): View
    {
        $object->load(['objectType', 'vicariate', 'deanery', 'sanctities', 'media']);

        return view('admin.objects.show', compact('object'));
    }

    public function edit(PilgrimageObject $object): View
    {
        $object->load(['sanctities', 'media']);

        return $this->formView($object);
    }

    public function update(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $data = $this->validated($request, $object);
        $sanctityIds = $data['sanctity_ids'] ?? [];
        $files = $request->file('media_files', []);
        unset($data['sanctity_ids'], $data['media_files']);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'], $object->id);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published']
            ? ($data['published_at'] ?? $object->published_at ?? now())
            : null;

        DB::transaction(function () use ($object, $data, $sanctityIds) {
            $object->update($data);
            $object->sanctities()->sync($sanctityIds);
        });

        $this->storeUploadedMedia($object, $files);

        return redirect()
            ->route('admin.objects.edit', $object)
            ->with('success', 'Паломнический объект обновлён.');
    }

    public function destroy(PilgrimageObject $object): RedirectResponse
    {
        $object->delete();

        return redirect()
            ->route('admin.objects.index')
            ->with('success', 'Объект перемещён в архив.');
    }

    private function formView(PilgrimageObject $object): View
    {
        return view('admin.objects.form', [
            'object' => $object,
            'types' => ObjectType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'vicariates' => Vicariate::query()->orderBy('name')->get(),
            'deaneries' => Deanery::query()->with('vicariate')->orderBy('name')->get(),
            'sanctities' => Sanctity::query()->orderBy('name')->get(),
            'selectedSanctities' => $object->exists
                ? $object->sanctities->pluck('id')->all()
                : [],
        ]);
    }

    private function validated(Request $request, ?PilgrimageObject $object = null): array
    {
        $slugRule = Rule::unique('pilgrimage_objects', 'slug');
        if ($object) {
            $slugRule->ignore($object->id);
        }

        $deaneryRule = Rule::exists('deaneries', 'id');
        if ($request->filled('vicariate_id')) {
            $vicariateId = (int) $request->input('vicariate_id');
            $deaneryRule->where(fn ($query) => $query->where('vicariate_id', $vicariateId));
        }

        return $request->validate([
            'object_type_id' => ['required', 'integer', 'exists:object_types,id'],
            'vicariate_id' => ['nullable', 'required_with:deanery_id', 'integer', 'exists:vicariates,id'],
            'deanery_id' => ['nullable', 'integer', $deaneryRule],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'schedule_text' => ['nullable', 'string'],
            'parking_info' => ['nullable', 'string'],
            'accessibility_info' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sanctity_ids' => ['nullable', 'array'],
            'sanctity_ids.*' => ['integer', 'exists:sanctities,id'],
            'media_files' => ['nullable', 'array', 'max:20'],
            'media_files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,mp3,wav,m4a,mp4,mov,avi,pdf,doc,docx'],
        ]);
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $base = $base !== '' ? $base : 'object';
        $candidate = $base;
        $counter = 2;

        while (PilgrimageObject::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }

    private function storeUploadedMedia(PilgrimageObject $object, array $files): void
    {
        if (empty($files)) {
            return;
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
    }
}
