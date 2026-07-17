<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JointPilgrimage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organizer_id',
        'pilgrimage_route_id',
        'title',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'meeting_place',
        'max_participants',
        'transport_mode',
        'join_mode',
        'contact_method',
        'contact_value',
        'status',
        'moderated_by',
        'moderated_at',
        'moderation_note',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_participants' => 'integer',
        'moderated_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function pilgrimageRoute(): BelongsTo
    {
        return $this->belongsTo(PilgrimageRoute::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(JointPilgrimageMember::class);
    }

    public function approvedMembers(): HasMany
    {
        return $this->members()->where('status', 'approved');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(JointPilgrimageMessage::class)->oldest();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    public function approvedParticipantsCount(): int
    {
        $count = array_key_exists('approved_members_count', $this->attributes)
            ? (int) $this->attributes['approved_members_count']
            : $this->approvedMembers()->count();

        return $count + 1;
    }

    public function availablePlaces(): ?int
    {
        if ($this->max_participants === null) {
            return null;
        }

        return max(0, $this->max_participants - $this->approvedParticipantsCount());
    }

    public function isFull(): bool
    {
        return $this->max_participants !== null && $this->availablePlaces() === 0;
    }
}
