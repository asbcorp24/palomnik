<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Services\AchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function store(
        Request $request,
        PilgrimageObject $object,
        AchievementService $achievementService
    ): RedirectResponse {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $alreadyMarked = $request->user()->visits()
            ->where('pilgrimage_object_id', $object->id)
            ->whereDate('visited_at', today())
            ->exists();

        if ($alreadyMarked) {
            return back()->with('error', 'Сегодня вы уже отмечались на этом объекте.');
        }

        $hasCoordinates = isset($data['latitude'], $data['longitude']);
        $distance = $hasCoordinates
            ? $this->distanceMeters(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $object->latitude,
                (float) $object->longitude
            )
            : null;
        $verified = $distance !== null && $distance <= 250;

        $request->user()->visits()->create([
            'pilgrimage_object_id' => $object->id,
            'visited_at' => now(),
            'verification_method' => $hasCoordinates ? 'geolocation' : 'manual',
            'status' => $verified ? 'verified' : 'pending',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $distance !== null
                ? 'Расстояние до объекта при отметке: '.round($distance).' м.'
                : 'Отметка без передачи геолокации.',
        ]);

        $message = $verified
            ? 'Посещение подтверждено по геолокации.'
            : 'Отметка сохранена и отправлена на проверку.';

        if ($verified) {
            $awarded = $achievementService->evaluate($request->user());
            if ($awarded) {
                $message .= ' Новое достижение: '.implode(', ', $awarded).'.';
            }
        }

        return back()->with('success', $message);
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
