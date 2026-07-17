<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;

class AchievementService
{
    public function evaluate(User $user): array
    {
        $awarded = [];
        $verifiedVisits = $user->visits()
            ->where('status', 'verified')
            ->distinct('pilgrimage_object_id')
            ->count('pilgrimage_object_id');

        $familyTrips = $user->bookings()
            ->where('status', 'completed')
            ->whereHas('trip.pilgrimageRoute', fn ($query) => $query->where('category', 'family'))
            ->count();

        $achievements = Achievement::query()
            ->where('is_active', true)
            ->whereIn('condition_type', ['visits_count', 'family_trips_count'])
            ->get();

        foreach ($achievements as $achievement) {
            $current = match ($achievement->condition_type) {
                'visits_count' => $verifiedVisits,
                'family_trips_count' => $familyTrips,
                default => 0,
            };

            $target = (int) ($achievement->condition_value ?? 0);
            $existing = $user->achievements()->whereKey($achievement->id)->exists();

            if ($target > 0 && $current >= $target && ! $existing) {
                $user->achievements()->attach($achievement->id, [
                    'awarded_at' => now(),
                    'progress' => json_encode(['current' => $current, 'target' => $target]),
                ]);
                $awarded[] = $achievement->title;
            }
        }

        return $awarded;
    }
}
