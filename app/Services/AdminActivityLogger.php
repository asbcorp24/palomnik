<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminActivityLogger
{
    private static int $disabledDepth = 0;

    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
        ?int $userId = null,
        string $source = 'web',
        ?string $batchId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityLabel = null
    ): ?AdminActivityLog {
        if (self::$disabledDepth > 0) {
            return null;
        }

        try {
            if (! Schema::hasTable('admin_activity_logs')) {
                return null;
            }

            $request = app()->bound('request') ? request() : null;
            $resolvedUserId = $userId ?? Auth::id();

            return AdminActivityLog::query()->create([
                'user_id' => $resolvedUserId,
                'action' => mb_substr($action, 0, 80),
                'entity_type' => $entityType ?: ($entity ? get_class($entity) : null),
                'entity_id' => $entityId ?? ($entity?->getKey() ? (int) $entity->getKey() : null),
                'entity_label' => mb_substr(
                    $entityLabel ?: $this->entityLabel($entity),
                    0,
                    500
                ),
                'old_values' => $this->sanitize($oldValues),
                'new_values' => $this->sanitize($newValues),
                'context' => $this->sanitize($context),
                'ip_address' => $request?->ip(),
                'request_method' => $request?->method(),
                'request_path' => $request ? mb_substr($request->path(), 0, 1000) : null,
                'source' => mb_substr($source, 0, 40),
                'batch_id' => $batchId ? mb_substr($batchId, 0, 64) : null,
                'user_agent' => $request?->userAgent()
                    ? mb_substr((string) $request->userAgent(), 0, 5000)
                    : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function snapshot(Model $model): array
    {
        return $this->sanitize($model->getAttributes()) ?? [];
    }

    public function runWithoutLogging(callable $callback): mixed
    {
        self::$disabledDepth++;

        try {
            return $callback();
        } finally {
            self::$disabledDepth = max(0, self::$disabledDepth - 1);
        }
    }

    public function isAdministratorAction(): bool
    {
        $user = Auth::user();

        return $user !== null
            && method_exists($user, 'isAdmin')
            && $user->isAdmin();
    }

    private function entityLabel(?Model $entity): string
    {
        if (! $entity) {
            return '';
        }

        foreach (['name', 'title', 'key', 'slug'] as $field) {
            $value = $entity->getAttribute($field);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($entity).' #'.$entity->getKey();
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|secret|token|remember|api[_-]?key|cookie|authorization/i', $key)) {
            return '[СКРЫТО]';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Model) {
            return $this->sanitize($value->getAttributes());
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $itemKey => $itemValue) {
                $clean[$itemKey] = $this->sanitize($itemValue, (string) $itemKey);
            }

            return $clean;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value);
        }

        if (is_string($value) && strlen($value) > 200000) {
            return mb_substr($value, 0, 200000).'… [обрезано]';
        }

        return $value;
    }
}
