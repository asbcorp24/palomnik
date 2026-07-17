<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObjectRepresentative extends Model
{
    use HasFactory;

    protected $fillable = [
        'pilgrimage_object_id',
        'user_id',
        'role',
        'status',
        'verified_by',
        'verified_at',
        'note',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function pilgrimageObject(): BelongsTo
    {
        return $this->belongsTo(PilgrimageObject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
