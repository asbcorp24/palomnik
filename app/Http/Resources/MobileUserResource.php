<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileUserResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $roleLabels = User::roleLabels();
        $roleDescriptions = User::roleDescriptions();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'birth_date' => optional($user->birth_date)->format('Y-m-d'),
            'preferences' => $user->preferences ?: [],
            'email_verified' => $user->hasVerifiedEmail(),
            'is_verified_organizer' => (bool) $user->is_verified_organizer,
            'role' => $user->role,
            'role_label' => $roleLabels[$user->role] ?? $user->role,
            'role_description' => $roleDescriptions[$user->role] ?? '',
            'capabilities' => $this->capabilities($user),
            'workspaces' => $this->workspaces($user),
        ];
    }

    private function capabilities(User $user): array
    {
        return [
            'backoffice_access' => $user->canAccessBackoffice(),
            'service_access' => $user->hasPermission(User::PERMISSION_SERVICE_ACCESS),
            'assigned_objects_manage' => $user->hasPermission(User::PERMISSION_ASSIGNED_OBJECTS_MANAGE),
            'bookings_manage' => $user->hasPermission(User::PERMISSION_BOOKINGS_MANAGE),
            'moderation_manage' => $user->hasPermission(User::PERMISSION_MODERATION_MANAGE),
            'content_manage' => $user->hasPermission(User::PERMISSION_CONTENT_MANAGE),
            'users_view' => $user->hasPermission(User::PERMISSION_USERS_VIEW),
            'users_manage' => $user->hasPermission(User::PERMISSION_USERS_MANAGE),
            'activity_view' => $user->hasPermission(User::PERMISSION_ACTIVITY_VIEW),
            'system_manage' => $user->hasPermission(User::PERMISSION_SYSTEM_MANAGE),
            'routes_manage' => $user->hasPermission(User::PERMISSION_ROUTES_MANAGE),
            'trips_manage' => $user->hasPermission(User::PERMISSION_TRIPS_MANAGE),
        ];
    }

    private function workspaces(User $user): array
    {
        $workspaces = [];

        if ($user->hasPermission(User::PERMISSION_SERVICE_ACCESS)) {
            $workspaces[] = [
                'code' => 'service',
                'label' => $user->role === User::ROLE_OBJECT_EDITOR
                    ? 'Кабинет представителя объекта'
                    : 'Работа с закреплёнными объектами',
                'description' => 'Заявки на изменения, сведения и материалы закреплённых храмов и монастырей.',
                'url' => url('/service'),
                'icon' => 'church',
            ];
        }

        if ($user->canAccessBackoffice()) {
            $workspaces[] = [
                'code' => 'backoffice',
                'label' => $this->backofficeLabel($user),
                'description' => $this->backofficeDescription($user),
                'url' => url('/admin'),
                'icon' => $user->isModerator() ? 'verified_user' : 'dashboard',
            ];
        }

        return $workspaces;
    }

    private function backofficeLabel(User $user): string
    {
        return match ($user->role) {
            User::ROLE_SERVICE_MANAGER => 'Панель паломнической службы',
            User::ROLE_MODERATOR => 'Панель модератора',
            User::ROLE_SUPER_ADMIN => 'Панель главного администратора',
            default => 'Панель управления',
        };
    }

    private function backofficeDescription(User $user): string
    {
        return match ($user->role) {
            User::ROLE_SERVICE_MANAGER => 'Маршруты, поездки, CRM заявок, участники и проверка QR-билетов.',
            User::ROLE_MODERATOR => 'Очереди модерации, жалобы, пользовательские материалы и совместные паломничества.',
            User::ROLE_SUPER_ADMIN => 'Полный доступ к платформе, ролям пользователей и системным настройкам.',
            default => 'Каталог, маршруты, CRM, модерация, пользователи и журнал действий.',
        };
    }
}
