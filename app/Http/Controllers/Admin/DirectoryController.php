<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\Sanctity;
use App\Models\Vicariate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $config = $this->config($resource);
        $query = $config['model']::query();
        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($resource === 'deaneries') {
            $query->with('vicariate');
        }

        $items = $query
            ->orderBy($resource === 'object-types' ? 'sort_order' : 'name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.directories.index', compact('resource', 'config', 'items', 'search'));
    }

    public function create(string $resource): View
    {
        $config = $this->config($resource);
        $item = new $config['model'];

        return view('admin.directories.form', [
            'resource' => $resource,
            'config' => $config,
            'item' => $item,
            'vicariates' => $resource === 'deaneries'
                ? Vicariate::query()->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $data = $this->validated($request, $resource);
        $data['slug'] = $this->makeUniqueSlug($config['model'], $data['slug'] ?? null, $data['name']);

        $config['model']::query()->create($data);

        return redirect()
            ->route('admin.directories.index', $resource)
            ->with('success', $config['single'].' создано.');
    }

    public function edit(string $resource, int $id): View
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);

        return view('admin.directories.form', [
            'resource' => $resource,
            'config' => $config,
            'item' => $item,
            'vicariates' => $resource === 'deaneries'
                ? Vicariate::query()->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);
        $data = $this->validated($request, $resource, $item);
        $data['slug'] = $this->makeUniqueSlug($config['model'], $data['slug'] ?? null, $data['name'], $item->getKey());
        $item->update($data);

        return redirect()
            ->route('admin.directories.index', $resource)
            ->with('success', $config['single'].' обновлено.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);

        if ($resource === 'object-types' && $item->pilgrimageObjects()->exists()) {
            return back()->with('error', 'Тип используется в объектах и не может быть удалён.');
        }

        if ($resource === 'vicariates' && ($item->deaneries()->exists() || $item->pilgrimageObjects()->exists())) {
            return back()->with('error', 'Викариатство связано с благочиниями или объектами. Сначала измените связанные записи.');
        }

        if ($resource === 'deaneries' && $item->pilgrimageObjects()->exists()) {
            return back()->with('error', 'Благочиние используется в объектах и не может быть удалено.');
        }

        $item->delete();

        return redirect()
            ->route('admin.directories.index', $resource)
            ->with('success', $config['single'].' удалено.');
    }

    private function validated(Request $request, string $resource, ?Model $item = null): array
    {
        $table = $this->config($resource)['table'];
        $uniqueSlug = Rule::unique($table, 'slug');

        if ($item) {
            $uniqueSlug->ignore($item->getKey());
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $uniqueSlug],
        ];

        if ($resource === 'object-types') {
            $rules += [
                'marker_color' => ['nullable', 'string', 'max:16'],
                'icon' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            ];
        } elseif ($resource === 'deaneries') {
            $rules += [
                'vicariate_id' => ['required', 'integer', 'exists:vicariates,id'],
                'description' => ['nullable', 'string'],
            ];
        } else {
            $rules['description'] = ['nullable', 'string'];
        }

        if ($resource === 'sanctities') {
            $rules['type'] = ['nullable', 'string', 'max:64'];
        }

        $data = $request->validate($rules);

        if ($resource === 'object-types') {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }

        return $data;
    }

    private function makeUniqueSlug(string $modelClass, ?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $base = $base !== '' ? $base : 'item';
        $candidate = $base;
        $counter = 2;

        while ($modelClass::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }

    private function config(string $resource): array
    {
        $resources = [
            'object-types' => [
                'model' => ObjectType::class,
                'table' => 'object_types',
                'title' => 'Типы объектов',
                'single' => 'Тип объекта',
                'icon' => 'bi-pin-map',
            ],
            'vicariates' => [
                'model' => Vicariate::class,
                'table' => 'vicariates',
                'title' => 'Викариатства',
                'single' => 'Викариатство',
                'icon' => 'bi-diagram-3',
            ],
            'deaneries' => [
                'model' => Deanery::class,
                'table' => 'deaneries',
                'title' => 'Благочиния',
                'single' => 'Благочиние',
                'icon' => 'bi-building',
            ],
            'sanctities' => [
                'model' => Sanctity::class,
                'table' => 'sanctities',
                'title' => 'Святыни',
                'single' => 'Святыня',
                'icon' => 'bi-star',
            ],
        ];

        abort_unless(isset($resources[$resource]), 404);

        return $resources[$resource];
    }
}
