<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\PilgrimageObject;
use App\Models\SiteColorScheme;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['nullable', 'string', 'max:191'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = AdminActivityLog::query()
            ->with('user:id,name,email')
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('entity_label', 'like', "%{$term}%")
                        ->orWhere('action', 'like', "%{$term}%")
                        ->orWhere('request_path', 'like', "%{$term}%")
                        ->orWhere('batch_id', 'like', "%{$term}%");
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['entity_type'] ?? null, fn (Builder $query, string $type) => $query->where('entity_type', $type))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', $date.' 23:59:59'))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AdminActivityLog::actionLabels(),
            'entityTypes' => AdminActivityLog::query()
                ->whereNotNull('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
            'users' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function show(AdminActivityLog $activityLog): View
    {
        $activityLog->load('user:id,name,email');

        return view('admin.activity.show', [
            'log' => $activityLog,
            'changedFields' => $this->changedFields(
                $activityLog->old_values ?? [],
                $activityLog->new_values ?? []
            ),
            'canRestore' => $this->canRestore($activityLog),
        ]);
    }

    public function restore(
        Request $request,
        AdminActivityLog $activityLog,
        AdminActivityLogger $logger
    ): RedirectResponse {
        $data = $request->validate([
            'snapshot' => ['required', Rule::in(['old', 'new'])],
        ]);

        $snapshot = $data['snapshot'] === 'old'
            ? $activityLog->old_values
            : $activityLog->new_values;

        if (! is_array($snapshot) || ! $this->canRestore($activityLog)) {
            return back()->with('error', 'Для этой записи восстановление недоступно.');
        }

        $before = null;
        $after = null;
        $entity = null;

        try {
            DB::transaction(function () use (
                $activityLog,
                $snapshot,
                $logger,
                &$before,
                &$after,
                &$entity
            ): void {
                if ($activityLog->entity_type === PilgrimageObject::class) {
                    $entity = PilgrimageObject::withTrashed()->findOrFail($activityLog->entity_id);
                    $before = $logger->snapshot($entity);

                    $logger->runWithoutLogging(function () use ($entity, $snapshot): void {
                        $this->restorePilgrimageObject($entity, $snapshot);
                    });

                    $entity = PilgrimageObject::withTrashed()->findOrFail($activityLog->entity_id);
                    $after = $logger->snapshot($entity);
                    return;
                }

                if ($activityLog->entity_type === SiteColorScheme::class) {
                    $entity = SiteColorScheme::query()->findOrFail($activityLog->entity_id);
                    $before = $logger->snapshot($entity);

                    $logger->runWithoutLogging(function () use ($entity, $snapshot): void {
                        $this->restoreColorScheme($entity, $snapshot);
                    });

                    $entity->refresh();
                    $after = $logger->snapshot($entity);
                    return;
                }

                if ($activityLog->entity_type === SiteSetting::class) {
                    $entity = SiteSetting::query()->findOrFail($activityLog->entity_id);
                    $before = $logger->snapshot($entity);

                    $logger->runWithoutLogging(function () use ($entity, $snapshot): void {
                        $values = Arr::only($snapshot, ['key', 'value']);
                        $values['value'] = $this->decodedArray($values['value'] ?? []);
                        $entity->update($values);
                    });

                    $entity->refresh();
                    $after = $logger->snapshot($entity);
                    return;
                }

                throw new RuntimeException('Этот тип записи нельзя восстановить автоматически.');
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Не удалось восстановить редакцию. Изменения отменены транзакцией.');
        }

        $logger->log(
            'revision_restored',
            $entity,
            $before,
            $after,
            [
                'source_log_id' => $activityLog->id,
                'restored_snapshot' => $data['snapshot'],
            ]
        );

        return redirect()
            ->route('admin.activity.show', $activityLog)
            ->with('success', 'Редакция восстановлена. Новое действие записано в журнал.');
    }

    private function restorePilgrimageObject(PilgrimageObject $object, array $snapshot): void
    {
        $slug = trim((string) ($snapshot['slug'] ?? $object->slug));
        $slugConflict = PilgrimageObject::withTrashed()
            ->where('slug', $slug)
            ->where('id', '<>', $object->id)
            ->exists();

        if ($slugConflict) {
            throw new RuntimeException('Нельзя восстановить редакцию: её URL-идентификатор уже занят другим объектом.');
        }

        if ($object->trashed()) {
            $object->restore();
            $object->refresh();
        }

        $values = Arr::only($snapshot, $object->getFillable());
        $object->fill($values);
        $object->save();

        if (! empty($snapshot['deleted_at']) && ! $object->trashed()) {
            $object->delete();
        }
    }

    private function restoreColorScheme(SiteColorScheme $scheme, array $snapshot): void
    {
        $values = Arr::only($snapshot, ['name', 'slug', 'colors']);
        $values['colors'] = $this->decodedArray($values['colors'] ?? []);
        $shouldBeActive = (bool) ($snapshot['is_active'] ?? false);

        $scheme->update($values);

        if ($shouldBeActive) {
            $scheme->activate();
            return;
        }

        if (! $scheme->is_active) {
            return;
        }

        $replacement = SiteColorScheme::query()
            ->whereKeyNot($scheme->id)
            ->orderByDesc('is_active')
            ->oldest('id')
            ->first();

        if (! $replacement) {
            throw new RuntimeException('Нельзя сделать единственную цветовую схему неактивной.');
        }

        $replacement->activate();
        $scheme->refresh();
    }

    private function canRestore(AdminActivityLog $log): bool
    {
        if (! is_array($log->old_values) && ! is_array($log->new_values)) {
            return false;
        }

        return in_array($log->entity_type, [
            PilgrimageObject::class,
            SiteColorScheme::class,
            SiteSetting::class,
        ], true) && $log->entity_id !== null;
    }

    private function changedFields(array $old, array $new): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
        $changed = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }

    private function decodedArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
