<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_PILGRIM = 'pilgrim';
    public const ROLE_OBJECT_EDITOR = 'object_editor';
    public const ROLE_SERVICE_MANAGER = 'service_manager';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar_path',
        'birth_date',
        'preferences',
        'is_active',
        'is_verified_organizer',
        'verified_organizer_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'preferences' => 'array',
        'is_active' => 'boolean',
        'is_verified_organizer' => 'boolean',
        'verified_organizer_at' => 'datetime',
    ];

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function canManageObjects(): bool
    {
        return $this->isAdmin() || in_array($this->role, [self::ROLE_OBJECT_EDITOR, self::ROLE_SERVICE_MANAGER], true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(UserMedia::class);
    }

    public function favoriteLists(): HasMany
    {
        return $this->hasMany(FavoriteList::class);
    }

    public function routePlans(): HasMany
    {
        return $this->hasMany(UserRoutePlan::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    public function organizedJointPilgrimages(): HasMany
    {
        return $this->hasMany(JointPilgrimage::class, 'organizer_id');
    }

    public function jointPilgrimageMemberships(): HasMany
    {
        return $this->hasMany(JointPilgrimageMember::class);
    }

    public function jointPilgrimageMessages(): HasMany
    {
        return $this->hasMany(JointPilgrimageMessage::class);
    }

    public function objectRepresentatives(): HasMany
    {
        return $this->hasMany(ObjectRepresentative::class);
    }

    public function objectUpdateRequests(): HasMany
    {
        return $this->hasMany(ObjectUpdateRequest::class);
    }

    public function objectMediaSubmissions(): HasMany
    {
        return $this->hasMany(ObjectMediaSubmission::class);
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(CommunityReport::class, 'reporter_id');
    }

    public function receivedReports(): HasMany
    {
        return $this->hasMany(CommunityReport::class, 'reported_user_id');
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function hasBlocked(User $user): bool
    {
        return $this->blockedUsers()->where('blocked_id', $user->id)->exists();
    }

    public function isBlockedBy(User $user): bool
    {
        return $this->blockedByUsers()->where('blocker_id', $user->id)->exists();
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot(['awarded_at', 'progress'])
            ->withTimestamps();
    }
}
