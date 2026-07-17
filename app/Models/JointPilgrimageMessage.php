<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JointPilgrimageMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'joint_pilgrimage_id',
        'user_id',
        'body',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function jointPilgrimage(): BelongsTo
    {
        return $this->belongsTo(JointPilgrimage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
