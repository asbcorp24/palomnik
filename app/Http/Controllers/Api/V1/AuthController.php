<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FavoriteList;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:64', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'consent' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($request, $data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?: null,
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_PILGRIM,
                'is_active' => true,
                'preferences' => [
                    'notifications' => true,
                    'privacy' => 'private',
                    'theme' => 'system',
                    'font_size' => 'normal',
                    'interests' => [],
                ],
            ]);

            FavoriteList::query()->create([
                'user_id' => $user->id,
                'name' => 'Избранное',
                'is_default' => true,
            ]);

            $user->consents()->create([
                'type' => 'personal_data_processing',
                'policy_version' => config('palomnik.privacy.policy_version', '1.0'),
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            ]);

            $token = $user->createToken($data['device_name'] ?? 'Flutter mobile')->plainTextToken;

            return compact('user', 'token');
        });

        return response()->json([
            'token' => $result['token'],
            'user' => $this->userData($result['user']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Учётная запись заблокирована.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'Flutter mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userData($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Вы вышли из приложения.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'birth_date' => optional($user->birth_date)->format('Y-m-d'),
            'preferences' => $user->preferences ?: [],
            'is_verified_organizer' => (bool) $user->is_verified_organizer,
        ];
    }
}
