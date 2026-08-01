<?php

namespace App\Observers;

use App\Services\AdminActivityLogger;
use Illuminate\Database\Eloquent\Model;

class AdminAuditableObserver
{
    private AdminActivityLogger $logger;

    /** @var array<int, array<string, mixed>> */
    private array $beforeSnapshots = [];

    public function __construct(AdminActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function created(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $this->logger->log(
            'created',
            $model,
            null,
            $this->logger->snapshot($model),
            ['changed_fields' => array_keys($model->getAttributes())]
        );
    }

    public function updating(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $this->beforeSnapshots[spl_object_id($model)] = $this->sanitizeOriginal($model);
    }

    public function updated(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $objectId = spl_object_id($model);
        $before = $this->beforeSnapshots[$objectId] ?? $this->sanitizeOriginal($model);
        unset($this->beforeSnapshots[$objectId]);

        $changedFields = array_values(array_diff(array_keys($model->getChanges()), ['updated_at']));
        if ($changedFields === []) {
            return;
        }

        $this->logger->log(
            'updated',
            $model,
            $before,
            $this->logger->snapshot($model),
            ['changed_fields' => $changedFields]
        );
    }

    public function deleting(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $this->beforeSnapshots[spl_object_id($model)] = $this->logger->snapshot($model);
    }

    public function deleted(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $objectId = spl_object_id($model);
        $before = $this->beforeSnapshots[$objectId] ?? $this->sanitizeOriginal($model);
        unset($this->beforeSnapshots[$objectId]);

        $this->logger->log(
            'deleted',
            $model,
            $before,
            $this->logger->snapshot($model),
            ['soft_delete' => method_exists($model, 'trashed')]
        );
    }

    public function restoring(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $this->beforeSnapshots[spl_object_id($model)] = $this->logger->snapshot($model);
    }

    public function restored(Model $model): void
    {
        if (! $this->logger->isAdministratorAction()) {
            return;
        }

        $objectId = spl_object_id($model);
        $before = $this->beforeSnapshots[$objectId] ?? $this->sanitizeOriginal($model);
        unset($this->beforeSnapshots[$objectId]);

        $this->logger->log(
            'restored',
            $model,
            $before,
            $this->logger->snapshot($model)
        );
    }

    private function sanitizeOriginal(Model $model): array
    {
        $attributes = [];
        foreach ($model->getAttributes() as $key => $value) {
            $attributes[$key] = $model->getRawOriginal($key, $value);
        }

        return $attributes;
    }
}
