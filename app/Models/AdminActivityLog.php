<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'old_values',
        'new_values',
        'context',
        'ip_address',
        'request_method',
        'request_path',
        'source',
        'batch_id',
        'user_agent',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function actionLabels(): array
    {
        return [
            'created' => 'Создано',
            'updated' => 'Изменено',
            'deleted' => 'Перемещено в архив',
            'restored' => 'Восстановлено из архива',
            'revision_restored' => 'Восстановлена редакция',
            'merged' => 'Объекты объединены',
            'duplicate_ignored' => 'Совпадение проигнорировано',
            'duplicate_separate' => 'Оставлено отдельными объектами',
            'parent_assigned' => 'Назначен родительский объект',
            'bulk_publish' => 'Массовая публикация',
            'bulk_unpublish' => 'Массовое снятие с публикации',
            'bulk_set_type' => 'Массовое назначение типа',
            'bulk_set_deanery' => 'Массовое назначение благочиния',
            'bulk_add_route' => 'Массовое добавление в маршрут',
            'bulk_mark_review' => 'Массовая отправка на проверку',
            'bulk_archive' => 'Массовое архивирование',
            'bulk_export' => 'Экспорт объектов',
            'import' => 'Импорт данных',
        ];
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    public function getEntityShortTypeAttribute(): ?string
    {
        if (! $this->entity_type) {
            return null;
        }

        return class_basename($this->entity_type);
    }
}
