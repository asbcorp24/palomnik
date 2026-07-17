<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MobileProfileController extends Controller
{
    public function show(Request $request, AchievementService $achievementService): JsonResponse
    {
        $achievementService->evaluate($request->user());
        $user = $request->user()->loadCount([
            'bookings',
            'visits',
            'reviews',
            'blogPosts',
            'media',
            'favoriteLists',
            'achievements',
        ]);

        return response()->json([
            'user' => $this->userData($user),
            'stats' => [
                'bookings' => $user->bookings_count,
                'visits' => $user->visits_count,
                'reviews' => $user->reviews_count,
                'posts' => $user->blog_posts_count,
                'media' => $user->media_count,
                'favorite_lists' => $user->favorite_lists_count,
                'achievements' => $user->achievements_count,
            ],
            'achievements' => $user->achievements()->get()->map(fn (Achievement $achievement) => [
                'id' => $achievement->id,
                'slug' => $achievement->slug,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'points' => $achievement->points,
                'badge_level' => $achievement->badge_level,
                'icon' => $achievement->icon,
                'earned' => true,
                'awarded_at' => $this->dateValue($achievement->pivot->awarded_at ?? null),
                'progress' => $achievement->pivot->progress ?? null,
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:64', Rule::unique('users', 'phone')->ignore($user->id)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'notifications' => ['nullable', 'boolean'],
            'privacy' => ['required', Rule::in(['private', 'registered', 'public'])],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'font_size' => ['required', Rule::in(['normal', 'large', 'extra_large'])],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:64'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $preferences = $user->preferences ?: [];
        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => ($data['phone'] ?? null) ?: null,
            'birth_date' => array_key_exists('birth_date', $data) ? ($data['birth_date'] ?: null) : $user->birth_date,
            'preferences' => [
                'notifications' => $request->has('notifications')
                    ? $request->boolean('notifications')
                    : (bool) ($preferences['notifications'] ?? true),
                'privacy' => $data['privacy'],
                'theme' => $data['theme'],
                'font_size' => $data['font_size'],
                'interests' => array_values($data['interests'] ?? ($preferences['interests'] ?? [])),
            ],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['user' => $this->userData($user->fresh())]);
    }

    private function userData($user): array
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

    private function dateValue($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toIso8601String()
            : Carbon::parse((string) $value)->toIso8601String();
    }
}
