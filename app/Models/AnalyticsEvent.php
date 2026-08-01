<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'event',
        'entity_type',
        'entity_id',
        'search_query',
        'properties',
        'path',
        'referrer',
        'ip_hash',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'entity_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
