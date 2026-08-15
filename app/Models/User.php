<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_PILGRIM = 'pilgrim';
    public const ROLE_OBJECT_EDITOR = 'object_editor';
    public const ROLE_SERVICE_MANAGER = 'service_manager';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const PERMISSION_BACKOFFICE_ACCESS = 'backoffice.access';
    public const PERMISSION_SERVICE_ACCESS = 'service.access';
    public const PERMISSION_ASSIGNED_OBJECTS_MANAGE = 'assigned-objects.manage';
    public const PERMISSION_BOOKINGS_MANAGE = 'bookings.manage';
    public const PERMISSION_MODERATION_MANAGE = 'moderation.manage';
    public const PERMISSION_CONTENT_MANAGE = 'content.manage';
    public const PERMISSION_USERS_VIEW = 'users.view';
    public const PERMISSION_USERS_MANAGE = 'users.manage';
    public const PERMISSION_ACTIVITY_VIEW = 'activity.view';
    public const PERMISSION_SYSTEM_MANAGE = 'system.manage';
    public const PERMISSION_ROUTES_MANAGE = 'routes.manage';
    public const PERMISSION_TRIPS_MANAGE = 'trips.manage';

    private const ROLE_PERMISSIONS = [
        self::ROLE_PILGRIM => [],
        self::ROLE_OBJECT_EDITOR => [
            self::PERMISSION_SERVICE_ACCESS,
            self::PERMISSION_ASSIGNED_OBJECTS_MANAGE,
        ],
        self::ROLE_SERVICE_MANAGER => [
            self::PERMISSION_BACKOFFICE_ACCESS,
            self::PERMISSION_SERVICE_ACCESS,
            self::PERMISSION_ASSIGNED_OBJECTS_MANAGE,
            self::PERMISSION_BOOKINGS_MANAGE,
            self::PERMISSION_ROUTES_MANAGE,
            self::PERMISSION_TRIPS_MANAGE,
        ],
        self::ROLE_MODERATOR => [
            self::PERMISSION_BACKOFFICE_ACCESS,
            self::PERMISSION_MODERATION_MANAGE,
        ],
        self::ROLE_ADMIN => [
            self::PERMISSION_BACKOFFICE_ACCESS,
            self::PERMISSION_SERVICE_ACCESS,
            self::PERMISSION_ASSIGNED_OBJECTS_MANAGE,
            self::PERMISSION_BOOKINGS_MANAGE,
            self::PERMISSION_MODERATION_MANAGE,
            self::PERMISSION_CONTENT_MANAGE,
            self::PERMISSION_USERS_VIEW,
            self::PERMISSION_USERS_MANAGE,
            self::PERMISSION_ACTIVITY_VIEW,
            self::PERMISSION_ROUTES_MANAGE,
            self::PERMISSION_TRIPS_MANAGE,
        ],
        self::ROLE_SUPER_ADMIN => ['*'],
    ];

    protected $fillable = [
        'name',
        'email',
        'vk_id',
        'vk_avatar_url',
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

    public static function roleLabels(): array
    {
        return [
            self::ROLE_PILGRIM => 'Паломник',
            self::ROLE_OBJECT_EDITOR => 'Редактор объектов',
            self::ROLE_SERVICE_MANAGER => 'Паломническая служба',
            self::ROLE_MODERATOR => 'Модератор',
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_SUPER_ADMIN => 'Главный администратор',
        ];
    }

    public static function roleDescriptions(): array
    {
        return [
            self::ROLE_PILGRIM => 'Личный кабинет паломника, маршруты, бронирования, отзывы и публикации.',
            self::ROLE_OBJECT_EDITOR => 'Предлагает изменения и загружает материалы только для закреплённых объектов.',
            self::ROLE_SERVICE_MANAGER => 'Управляет маршрутами, поездками, заявками и проверкой электронных билетов.',
            self::ROLE_MODERATOR => 'Проверяет пользовательский контент, жалобы и изменения от представителей храмов.',
            self::ROLE_ADMIN => 'Управляет каталогом, справочниками, контентом, модерацией и обычными пользователями.',
            self::ROLE_SUPER_ADMIN => 'Имеет полный доступ, включая роли администраторов и системные настройки.',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : $this->vk_avatar_url;
    }

    public function hasVerifiedEmail()
    {
        if (filled($this->vk_id)) {
            return true;
        }

        return parent::hasVerifiedEmail();
    }

    public function sendEmailVerificationNotification(): void
    {
        if (filled($this->vk_id)) {
            return;
        }

        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = self::ROLE_PERMISSIONS[$this->role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isServiceManager(): bool
    {
        return $this->role === self::ROLE_SERVICE_MANAGER;
    }

    public function canAccessBackoffice(): bool
    {
        return $this->hasPermission(self::PERMISSION_BACKOFFICE_ACCESS);
    }

    public function canManageObjects(): bool
    {
        return $this->hasPermission(self::PERMISSION_ASSIGNED_OBJECTS_MANAGE);
    }

    public function canManageModule(string $resource): bool
    {
        if ($this->hasPermission(self::PERMISSION_CONTENT_MANAGE)) {
            return true;
        }

        return match ($resource) {
            'routes' => $this->hasPermission(self::PERMISSION_ROUTES_MANAGE),
            'trips' => $this->hasPermission(self::PERMISSION_TRIPS_MANAGE),
            default => false,
        };
    }

    public function canManageUser(User $target): bool
    {
        if (! $this->hasPermission(self::PERMISSION_USERS_MANAGE)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return ! in_array($target->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
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
