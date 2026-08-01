<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectDuplicateCandidate;
use App\Models\PilgrimageObject;
use App\Services\ObjectDuplicateDetectionService;
use App\Services\PilgrimageObjectMergeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ObjectDuplicateController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(ObjectDuplicateCandidate::statusLabels()))],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $status = $filters['status'] ?? ObjectDuplicateCandidate::STATUS_PENDING;

        $candidates = ObjectDuplicateCandidate::query()
            ->with([
                'objectA.objectType',
                'objectA.parentObject',
                'objectA.coverMedia',
                'objectB.objectType',
                'objectB.parentObject',
                'objectB.coverMedia',
                'reviewer:id,name',
            ])
            ->where('status', $status)
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereHas('objectA', function (Builder $query) use ($term): void {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%");
                    })->orWhereHas('objectB', function (Builder $query) use ($term): void {
                        $query->where('name', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%");
                    });
                });
            })
            ->when(isset($filters['min_score']), fn (Builder $query) => $query->where('score', '>=', (float) $filters['min_score']))
            ->orderByDesc('score')
            ->orderBy('distance_meters')
            ->paginate(20)
            ->withQueryString();

        $stats = ObjectDuplicateCandidate::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.duplicates.index', [
            'candidates' => $candidates,
            'filters' => $filters + ['status' => $status],
            'statuses' => ObjectDuplicateCandidate::statusLabels(),
            'stats' => $stats,
        ]);
    }

    public function scan(ObjectDuplicateDetectionService $service): RedirectResponse
    {
        set_time_limit(180);
        $result = $service->scan();

        return redirect()
            ->route('admin.duplicates.index')
            ->with('success', 'Проверено объектов: '.$result['objects']
                .'. Найдено кандидатов: '.$result['candidates']
                .'. Ожидают решения: '.$result['pending'].'.');
    }

    public function mark(
        Request $request,
        ObjectDuplicateCandidate $candidate
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                ObjectDuplicateCandidate::STATUS_IGNORED,
                ObjectDuplicateCandidate::STATUS_SEPARATE,
            ])],
        ]);

        $candidate->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $message = $data['status'] === ObjectDuplicateCandidate::STATUS_SEPARATE
            ? 'Объекты отмечены как самостоятельные.'
            : 'Совпадение добавлено в игнорируемые.';

        return back()->with('success', $message);
    }

    public function setParent(
        Request $request,
        ObjectDuplicateCandidate $candidate
    ): RedirectResponse {
        $pair = [(int) $candidate->object_a_id, (int) $candidate->object_b_id];
        $data = $request->validate([
            'parent_id' => ['required', 'integer', Rule::in($pair)],
        ]);

        $parentId = (int) $data['parent_id'];
        $childId = $parentId === (int) $candidate->object_a_id
            ? (int) $candidate->object_b_id
            : (int) $candidate->object_a_id;

        $parent = PilgrimageObject::query()->findOrFail($parentId);
        $child = PilgrimageObject::query()->findOrFail($childId);

        abort_if($this->wouldCreateCycle($parent, $child), 422, 'Такая связь создаст цикл в иерархии объектов.');

        $child->update(['parent_object_id' => $parent->id]);
        $candidate->update([
            'status' => ObjectDuplicateCandidate::STATUS_PARENTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Объект «'.$child->name.'» установлен в составе «'.$parent->name.'».');
    }

    public function merge(
        Request $request,
        ObjectDuplicateCandidate $candidate,
        PilgrimageObjectMergeService $service
    ): RedirectResponse {
        $pair = [(int) $candidate->object_a_id, (int) $candidate->object_b_id];
        $data = $request->validate([
            'master_id' => ['required', 'integer', Rule::in($pair)],
        ]);

        $masterId = (int) $data['master_id'];
        $duplicateId = $masterId === (int) $candidate->object_a_id
            ? (int) $candidate->object_b_id
            : (int) $candidate->object_a_id;

        $master = PilgrimageObject::query()->findOrFail($masterId);
        $duplicate = PilgrimageObject::query()->findOrFail($duplicateId);
        $duplicateName = $duplicate->name;

        $service->merge($master, $duplicate, $candidate, $request->user()->id);

        return redirect()
            ->route('admin.duplicates.index')
            ->with('success', 'Объект «'.$duplicateName.'» объединён с «'.$master->name.'». Связанные данные перенесены.');
    }

    private function wouldCreateCycle(PilgrimageObject $parent, PilgrimageObject $child): bool
    {
        $current = $parent;
        $visited = [];

        while ($current) {
            if ((int) $current->id === (int) $child->id) {
                return true;
            }

            if (isset($visited[$current->id])) {
                return true;
            }

            $visited[$current->id] = true;
            $current = $current->parentObject;
        }

        return false;
    }
}
