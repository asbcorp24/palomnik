<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JointPilgrimageMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'joint_pilgrimage_id',
        'user_id',
        'status',
        'message',
        'joined_at',
        'responded_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'responded_at' => 'datetime',
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
