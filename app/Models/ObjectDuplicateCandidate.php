<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectDuplicateCandidate extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_SEPARATE = 'separate';
    public const STATUS_PARENTED = 'parented';
    public const STATUS_MERGED = 'merged';

    protected $fillable = [
        'object_a_id',
        'object_b_id',
        'score',
        'name_similarity',
        'distance_meters',
        'reasons',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'name_similarity' => 'float',
        'distance_meters' => 'integer',
        'reasons' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function objectA(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class, 'object_a_id')->withTrashed();
    }

    public function objectB(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class, 'object_b_id')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Ожидает решения',
            self::STATUS_IGNORED => 'Игнорировать совпадение',
            self::STATUS_SEPARATE => 'Оставить отдельно',
            self::STATUS_PARENTED => 'Установлена иерархия',
            self::STATUS_MERGED => 'Объединено',
        ];
    }
}
