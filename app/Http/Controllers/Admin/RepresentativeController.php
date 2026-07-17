<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectRepresentative;
use App\Models\PilgrimageObject;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepresentativeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $assignments = ObjectRepresentative::query()
            ->with(['user', 'pilgrimageObject'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $term = trim($term);
                $query->where(function ($query) use ($term) {
                    $query->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                        ->orWhereHas('pilgrimageObject', fn ($query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.representatives.index', [
            'assignments' => $assignments,
            'filters' => $filters,
            'users' => User::query()->whereIn('role', [User::ROLE_OBJECT_EDITOR, User::ROLE_SERVICE_MANAGER])->where('is_active', true)->orderBy('name')->get(),
            'objects' => PilgrimageObject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pilgrimage_object_id' => ['required', 'integer', 'exists:pilgrimage_objects,id'],
            'role' => ['required', Rule::in(['editor', 'manager'])],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $assignment = ObjectRepresentative::query()->updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'pilgrimage_object_id' => $data['pilgrimage_object_id'],
            ],
            [
                'role' => $data['role'],
                'status' => $data['status'],
                'verified_by' => $data['status'] === 'approved' ? $request->user()->id : null,
                'verified_at' => $data['status'] === 'approved' ? now() : null,
                'note' => $data['note'] ?? null,
            ]
        );

        if ($assignment->status === 'approved' && ! in_array($assignment->user->role, [User::ROLE_OBJECT_EDITOR, User::ROLE_SERVICE_MANAGER], true)) {
            $assignment->user->update(['role' => User::ROLE_OBJECT_EDITOR]);
        }

        $assignment->user->notify(new PlatformNotification(
            $assignment->status === 'approved' ? 'Доступ к карточке подтверждён' : 'Статус доступа изменён',
            'Вам назначен объект «'.$assignment->pilgrimageObject->name.'». Статус: '.$assignment->status.'.',
            route('service.dashboard'),
            'bi-building-check'
        ));

        return back()->with('success', 'Представитель назначен.');
    }

    public function update(Request $request, ObjectRepresentative $representative): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['editor', 'manager'])],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $representative->update([
            'role' => $data['role'],
            'status' => $data['status'],
            'verified_by' => $data['status'] === 'approved' ? $request->user()->id : null,
            'verified_at' => $data['status'] === 'approved' ? now() : null,
            'note' => $data['note'] ?? null,
        ]);

        $representative->user->notify(new PlatformNotification(
            'Статус доступа к объекту изменён',
            'Объект «'.$representative->pilgrimageObject->name.'»: '.$data['status'].'.',
            route('service.dashboard'),
            'bi-building'
        ));

        return back()->with('success', 'Назначение обновлено.');
    }

    public function destroy(ObjectRepresentative $representative): RedirectResponse
    {
        $user = $representative->user;
        $objectName = $representative->pilgrimageObject->name;
        $representative->delete();

        $user->notify(new PlatformNotification(
            'Доступ к объекту отозван',
            'Доступ к карточке «'.$objectName.'» был отозван администратором.',
            route('service.dashboard'),
            'bi-building-x'
        ));

        return back()->with('success', 'Доступ представителя удалён.');
    }
}
