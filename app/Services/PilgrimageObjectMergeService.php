<?php

namespace App\Services;

use App\Models\ObjectDuplicateCandidate;
use App\Models\PilgrimageObject;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PilgrimageObjectMergeService
{
    public function merge(
        PilgrimageObject $master,
        PilgrimageObject $duplicate,
        ?ObjectDuplicateCandidate $candidate = null,
        ?int $reviewedBy = null
    ): PilgrimageObject {
        if ($master->id === $duplicate->id) {
            throw new RuntimeException('Нельзя объединить объект с самим собой.');
        }

        return DB::transaction(function () use ($master, $duplicate, $candidate, $reviewedBy): PilgrimageObject {
            $master = PilgrimageObject::withTrashed()->lockForUpdate()->findOrFail($master->id);
            $duplicate = PilgrimageObject::withTrashed()->lockForUpdate()->findOrFail($duplicate->id);

            if ($master->trashed() || $duplicate->trashed()) {
                throw new RuntimeException('Один из объектов уже находится в архиве.');
            }

            if ((int) $master->parent_object_id === (int) $duplicate->id) {
                $master->parent_object_id = null;
            }

            $this->fillMissingFields($master, $duplicate);
            $master->save();

            PilgrimageObject::query()
                ->where('parent_object_id', $duplicate->id)
                ->where('id', '<>', $master->id)
                ->update(['parent_object_id' => $master->id]);

            $this->movePivotRows('object_sanctity', 'pilgrimage_object_id', $master->id, $duplicate->id, ['sanctity_id']);
            $this->movePivotRows('pilgrimage_route_object', 'pilgrimage_object_id', $master->id, $duplicate->id, ['pilgrimage_route_id']);
            $this->movePivotRows('favorite_list_object', 'pilgrimage_object_id', $master->id, $duplicate->id, ['favorite_list_id']);
            $this->movePivotRows('user_route_plan_object', 'pilgrimage_object_id', $master->id, $duplicate->id, ['user_route_plan_id']);

            $this->moveDirectRows('object_media', $master->id, $duplicate->id);
            $this->normalizeMedia($master->id);
            $this->moveDirectRows('object_update_requests', $master->id, $duplicate->id);
            $this->moveDirectRows('object_media_submissions', $master->id, $duplicate->id);
            $this->moveDirectRows('calendar_events', $master->id, $duplicate->id);
            $this->moveDirectRows('visits', $master->id, $duplicate->id);
            $this->moveDirectRows('points_of_interest', $master->id, $duplicate->id);
            $this->moveDirectRows('user_media', $master->id, $duplicate->id);
            $this->moveDirectRows('object_representatives', $master->id, $duplicate->id, ['user_id']);
            $this->moveDirectRows('reviews', $master->id, $duplicate->id, ['user_id']);

            $otherCandidates = ObjectDuplicateCandidate::query()
                ->where(function ($query) use ($duplicate): void {
                    $query->where('object_a_id', $duplicate->id)
                        ->orWhere('object_b_id', $duplicate->id);
                });

            if ($candidate) {
                $otherCandidates->where('id', '<>', $candidate->id);
            }

            $otherCandidates->delete();

            if ($candidate) {
                $candidate->update([
                    'status' => ObjectDuplicateCandidate::STATUS_MERGED,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => now(),
                ]);
            }

            $duplicate->delete();

            return $master->fresh();
        });
    }

    private function fillMissingFields(PilgrimageObject $master, PilgrimageObject $duplicate): void
    {
        foreach ([
            'short_description',
            'description',
            'history',
            'address',
            'phone',
            'email',
            'website',
            'schedule_text',
            'parking_info',
            'accessibility_info',
            'information_source_url',
            'information_verified_at',
            'verified_by',
            'next_verification_at',
            'verification_status',
            'vicariate_id',
            'deanery_id',
            'parent_object_id',
        ] as $field) {
            if (blank($master->{$field}) && filled($duplicate->{$field})) {
                $master->{$field} = $duplicate->{$field};
            }
        }

        if ((! is_numeric($master->latitude) || (float) $master->latitude === 0.0)
            && is_numeric($duplicate->latitude)
            && (float) $duplicate->latitude !== 0.0) {
            $master->latitude = $duplicate->latitude;
        }

        if ((! is_numeric($master->longitude) || (float) $master->longitude === 0.0)
            && is_numeric($duplicate->longitude)
            && (float) $duplicate->longitude !== 0.0) {
            $master->longitude = $duplicate->longitude;
        }

        if (! $master->is_published && $duplicate->is_published) {
            $master->is_published = true;
            $master->published_at = $master->published_at ?: $duplicate->published_at ?: now();
        }
    }

    private function movePivotRows(
        string $table,
        string $foreignKey,
        int $masterId,
        int $duplicateId,
        array $uniqueColumns
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        $rows = DB::table($table)->where($foreignKey, $duplicateId)->get();

        foreach ($rows as $row) {
            $data = (array) $row;
            unset($data['id']);
            $data[$foreignKey] = $masterId;

            $lookup = [$foreignKey => $masterId];
            foreach ($uniqueColumns as $column) {
                if (array_key_exists($column, $data)) {
                    $lookup[$column] = $data[$column];
                }
            }

            if (! DB::table($table)->where($lookup)->exists()) {
                DB::table($table)->insert($data);
            }
        }

        DB::table($table)->where($foreignKey, $duplicateId)->delete();
    }

    private function moveDirectRows(
        string $table,
        int $masterId,
        int $duplicateId,
        array $conflictColumns = []
    ): void {
        $foreignKey = 'pilgrimage_object_id';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
            return;
        }

        if ($conflictColumns === []) {
            DB::table($table)->where($foreignKey, $duplicateId)->update([$foreignKey => $masterId]);
            return;
        }

        $rows = DB::table($table)->where($foreignKey, $duplicateId)->get();

        foreach ($rows as $row) {
            $rowData = (array) $row;
            $lookup = [$foreignKey => $masterId];
            foreach ($conflictColumns as $column) {
                if (array_key_exists($column, $rowData)) {
                    $lookup[$column] = $rowData[$column];
                }
            }

            if (DB::table($table)->where($lookup)->exists()) {
                if (array_key_exists('id', $rowData)) {
                    DB::table($table)->where('id', $rowData['id'])->delete();
                }
                continue;
            }

            try {
                if (array_key_exists('id', $rowData)) {
                    DB::table($table)->where('id', $rowData['id'])->update([$foreignKey => $masterId]);
                }
            } catch (QueryException $exception) {
                if (array_key_exists('id', $rowData)) {
                    DB::table($table)->where('id', $rowData['id'])->delete();
                } else {
                    throw $exception;
                }
            }
        }
    }

    private function normalizeMedia(int $objectId): void
    {
        if (! Schema::hasTable('object_media')) {
            return;
        }

        $media = DB::table('object_media')
            ->where('pilgrimage_object_id', $objectId)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'type']);

        $coverId = optional($media->firstWhere('type', 'image'))->id;
        DB::table('object_media')
            ->where('pilgrimage_object_id', $objectId)
            ->update(['is_cover' => false]);

        foreach ($media->values() as $index => $item) {
            DB::table('object_media')->where('id', $item->id)->update([
                'sort_order' => $index + 1,
                'is_cover' => $coverId !== null && (int) $item->id === (int) $coverId,
            ]);
        }
    }
}
