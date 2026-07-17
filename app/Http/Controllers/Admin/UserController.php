<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:32'],
            'active' => ['nullable', 'in:0,1'],
        ]);

        $users = User::query()
            ->withCount(['visits', 'bookings', 'achievements', 'reviews', 'blogPosts'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when(array_key_exists('active', $filters), fn (Builder $query) => $query->where('is_active', (bool) $filters['active']))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roles(),
        ]);
    }

    public function show(User $user): View
    {
        $user->loadCount(['visits', 'bookings', 'achievements', 'reviews', 'blogPosts', 'media', 'favoriteLists', 'objectRepresentatives', 'receivedReports']);
        $user->load([
            'visits' => fn ($query) => $query->with('pilgrimageObject')->latest('visited_at')->limit(10),
            'bookings' => fn ($query) => $query->with('trip.pilgrimageRoute')->latest()->limit(10),
            'achievements' => fn ($query) => $query->orderByDesc('user_achievements.awarded_at'),
            'objectRepresentatives.pilgrimageObject',
        ]);

        return view('admin.users.show', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:64', Rule::unique('users', 'phone')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'is_active' => ['nullable', 'boolean'],
            'is_verified_organizer' => ['nullable', 'boolean'],
        ]);

        if ($user->is(auth()->user()) && ! $request->boolean('is_active')) {
            return back()->with('error', 'Нельзя отключить собственную учётную запись.');
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['is_verified_organizer'] = $request->boolean('is_verified_organizer');
        $data['verified_organizer_at'] = $data['is_verified_organizer'] ? ($user->verified_organizer_at ?? now()) : null;
        $user->update($data);

        return back()->with('success', 'Профиль пользователя обновлён.');
    }

    private function roles(): array
    {
        return [
            User::ROLE_PILGRIM => 'Паломник',
            User::ROLE_OBJECT_EDITOR => 'Редактор объектов',
            User::ROLE_SERVICE_MANAGER => 'Паломническая служба',
            User::ROLE_MODERATOR => 'Модератор',
            User::ROLE_ADMIN => 'Администратор',
            User::ROLE_SUPER_ADMIN => 'Главный администратор',
        ];
    }
}
