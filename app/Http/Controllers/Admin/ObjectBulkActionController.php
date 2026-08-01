<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectType;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Services\AdminActivityLogger;
use App\Services\PilgrimageObjectMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObjectBulkActionController extends Controller
{
    public function __invoke(
        Request $request,
        PilgrimageObjectMergeService $mergeService,
        AdminActivityLogger $logger
    ): RedirectResponse|StreamedResponse {
        $data = $request->validate([
            'object_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'object_ids.*' => ['required', 'integer', 'distinct', 'exists:pilgrimage_objects,id'],
            'action' => ['required', Rule::in([
                'publish',
                'unpublish',
                'set_type',
                'set_deanery',
                'add_route',
                'mark_review',
                'archive',
                'export',
                'merge',
            ])],
            'type_id' => ['nullable', 'integer', 'exists:object_types,id'],
            'deanery_id' => ['nullable', 'integer', 'exists:deaneries,id'],
            'route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'master_id' => ['nullable', 'integer'],
        ]);

        $ids = collect($data['object_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        $objects = PilgrimageObject::query()->whereKey($ids->all())->get();
        abort_unless($objects->count() === $ids->count(), 422, 'Часть выбранных объектов не найдена.');

        $batchId = 'bulk-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));
        $context = [
            'operation' => $data['action'],
            'object_ids' => $ids->all(),
            'objects_count' => $objects->count(),
            'type_id' => isset($data['type_id']) ? (int) $data['type_id'] : null,
            'deanery_id' => isset($data['deanery_id']) ? (int) $data['deanery_id'] : null,
            'route_id' => isset($data['route_id']) ? (int) $data['route_id'] : null,
            'master_id' => isset($data['master_id']) ? (int) $data['master_id'] : null,
        ];

        if ($data['action'] === 'export') {
            $logger->log(
                'bulk_export',
                null,
                null,
                null,
                $context,
                $request->user()->id,
                'web',
                $batchId,
                PilgrimageObject::class,
                null,
                'Экспорт выбранных объектов'
            );

            return $this->export($objects);
        }

        if ($data['action'] === 'merge') {
            abort_unless($objects->count() === 2, 422, 'Для объединения выберите ровно два объекта.');
            $masterId = (int) ($data['master_id'] ?? 0);
            abort_unless($ids->contains($masterId), 422, 'Выберите основной объект для объединения.');
            $master = $objects->firstWhere('id', $masterId);
            $duplicate = $objects->first(fn (PilgrimageObject $object): bool => (int) $object->id !== $masterId);
            $masterBefore = $logger->snapshot($master);
            $duplicateBefore = $logger->snapshot($duplicate);
            $duplicateName = $duplicate->name;
            $merged = $mergeService->merge($master, $duplicate, null, $request->user()->id);

            $logger->log(
                'merged',
                $merged,
                $masterBefore,
                $logger->snapshot($merged),
                $context + [
                    'duplicate_id' => $duplicate->id,
                    'duplicate_name' => $duplicateName,
                    'duplicate_snapshot' => $duplicateBefore,
                ],
                $request->user()->id,
                'web',
                $batchId
            );

            return back()->with('success', 'Объект «'.$duplicateName.'» объединён с «'.$master->name.'».');
        }

        $message = DB::transaction(function () use ($data, $objects): string {
            if ($data['action'] === 'publish') {
                foreach ($objects as $object) {
                    $object->update([
                        'is_published' => true,
                        'published_at' => $object->published_at ?: now(),
                    ]);
                }

                return 'Опубликовано объектов: '.$objects->count().'.';
            }

            if ($data['action'] === 'unpublish') {
                foreach ($objects as $object) {
                    $object->update([
                        'is_published' => false,
                        'published_at' => null,
                    ]);
                }

                return 'Снято с публикации объектов: '.$objects->count().'.';
            }

            if ($data['action'] === 'set_type') {
                $type = ObjectType::query()->where('is_active', true)->findOrFail((int) ($data['type_id'] ?? 0));
                foreach ($objects as $object) {
                    $object->update(['object_type_id' => $type->id]);
                }

                return 'Тип «'.$type->name.'» назначен объектам: '.$objects->count().'.';
            }

            if ($data['action'] === 'set_deanery') {
                $deanery = Deanery::query()->findOrFail((int) ($data['deanery_id'] ?? 0));
                foreach ($objects as $object) {
                    $object->update([
                        'deanery_id' => $deanery->id,
                        'vicariate_id' => $deanery->vicariate_id,
                    ]);
                }

                return 'Благочиние «'.$deanery->name.'» назначено объектам: '.$objects->count().'.';
            }

            if ($data['action'] === 'add_route') {
                $route = PilgrimageRoute::query()->findOrFail((int) ($data['route_id'] ?? 0));
                $sortOrder = (int) DB::table('pilgrimage_route_object')
                    ->where('pilgrimage_route_id', $route->id)
                    ->max('sort_order');
                $sync = [];
                foreach ($objects as $object) {
                    $exists = DB::table('pilgrimage_route_object')
                        ->where('pilgrimage_route_id', $route->id)
                        ->where('pilgrimage_object_id', $object->id)
                        ->exists();
                    if (! $exists) {
                        $sync[$object->id] = [
                            'sort_order' => ++$sortOrder,
                            'stay_minutes' => 30,
                            'note' => null,
                        ];
                    }
                }
                if ($sync !== []) {
                    $route->objects()->attach($sync);
                }

                return 'В маршрут «'.$route->title.'» добавлено объектов: '.count($sync).'.';
            }

            if ($data['action'] === 'mark_review') {
                foreach ($objects as $object) {
                    $object->update([
                        'verification_status' => PilgrimageObject::VERIFICATION_NEEDS_REVIEW,
                        'next_verification_at' => now(),
                    ]);
                }

                return 'На проверку отправлено объектов: '.$objects->count().'.';
            }

            if ($data['action'] === 'archive') {
                foreach ($objects as $object) {
                    $object->delete();
                }

                return 'Перемещено в архив объектов: '.$objects->count().'.';
            }

            return 'Операция выполнена.';
        });

        $logger->log(
            $this->auditAction($data['action']),
            null,
            null,
            null,
            $context,
            $request->user()->id,
            'web',
            $batchId,
            PilgrimageObject::class,
            null,
            'Массовая операция с объектами'
        );

        return back()->with('success', $message);
    }

    private function auditAction(string $action): string
    {
        return [
            'publish' => 'bulk_publish',
            'unpublish' => 'bulk_unpublish',
            'set_type' => 'bulk_set_type',
            'set_deanery' => 'bulk_set_deanery',
            'add_route' => 'bulk_add_route',
            'mark_review' => 'bulk_mark_review',
            'archive' => 'bulk_archive',
        ][$action] ?? 'updated';
    }

    private function export($objects): StreamedResponse
    {
        $objects->load(['objectType', 'vicariate', 'deanery']);
        $filename = 'pilgrimage-objects-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($objects): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Название',
                'Тип',
                'Адрес',
                'Широта',
                'Долгота',
                'Телефон',
                'Email',
                'Сайт',
                'Расписание',
                'Викариатство',
                'Благочиние',
                'Публикация',
                'Статус проверки',
                'Источник информации',
                'Изменён',
            ], ';');

            foreach ($objects->sortBy('name') as $object) {
                fputcsv($handle, [
                    $object->id,
                    $object->name,
                    $object->objectType?->name,
                    $object->address,
                    $object->latitude,
                    $object->longitude,
                    $object->phone,
                    $object->email,
                    $object->website,
                    $object->schedule_text,
                    $object->vicariate?->name,
                    $object->deanery?->name,
                    $object->is_published ? 'Опубликован' : 'Черновик',
                    PilgrimageObject::verificationStatusLabels()[$object->verification_status] ?? $object->verification_status,
                    $object->information_source_url,
                    optional($object->updated_at)->format('d.m.Y H:i:s'),
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
