<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\FavoriteList;
use App\Services\AchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function dashboard(Request $request, AchievementService $achievementService): View
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

        $latestBookings = $user->bookings()
            ->with('trip.pilgrimageRoute')
            ->latest()
            ->limit(3)
            ->get();

        $latestVisits = $user->visits()
            ->with('pilgrimageObject.objectType')
            ->latest('visited_at')
            ->limit(4)
            ->get();

        $latestAchievements = $user->achievements()
            ->orderByDesc('user_achievements.awarded_at')
            ->limit(4)
            ->get();

        return view('site.profile.dashboard', compact(
            'user',
            'latestBookings',
            'latestVisits',
            'latestAchievements'
        ));
    }

    public function settings(Request $request): View
    {
        return view('site.profile.settings', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
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

        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => ! empty($data['phone']) ? $data['phone'] : null,
            'birth_date' => ! empty($data['birth_date']) ? $data['birth_date'] : null,
            'preferences' => [
                'notifications' => $request->boolean('notifications'),
                'privacy' => $data['privacy'],
                'theme' => $data['theme'],
                'font_size' => $data['font_size'],
                'interests' => array_values($data['interests'] ?? []),
            ],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', 'Настройки профиля сохранены.');
    }

    public function favorites(Request $request): View
    {
        $lists = $request->user()->favoriteLists()
            ->with(['objects.objectType', 'objects.coverMedia'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($lists->isEmpty()) {
            $list = FavoriteList::query()->create([
                'user_id' => $request->user()->id,
                'name' => 'Избранное',
                'is_default' => true,
            ]);
            $lists = collect([$list->load('objects')]);
        }

        return view('site.profile.favorites', compact('lists'));
    }

    public function bookings(Request $request): View
    {
        $bookings = $request->user()->bookings()
            ->with('trip.pilgrimageRoute')
            ->latest()
            ->paginate(15);

        return view('site.profile.bookings', compact('bookings'));
    }

    public function achievements(Request $request, AchievementService $achievementService): View
    {
        $achievementService->evaluate($request->user());

        $earned = $request->user()->achievements()
            ->get()
            ->keyBy('id');

        $achievements = Achievement::query()
            ->where('is_active', true)
            ->orderBy('points')
            ->orderBy('title')
            ->get();

        return view('site.profile.achievements', compact('achievements', 'earned'));
    }

    public function activity(Request $request): View
    {
        $user = $request->user();

        return view('site.profile.activity', [
            'visits' => $user->visits()->with('pilgrimageObject')->latest('visited_at')->limit(20)->get(),
            'reviews' => $user->reviews()->with('pilgrimageObject')->latest()->limit(20)->get(),
            'posts' => $user->blogPosts()->latest()->limit(20)->get(),
            'mediaItems' => $user->media()->with('pilgrimageObject')->latest()->limit(20)->get(),
        ]);
    }
}
