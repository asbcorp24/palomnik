<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Deanery;
use App\Models\ObjectMedia;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Review;
use App\Models\Sanctity;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserMedia;
use App\Models\Vicariate;
use App\Models\Visit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'objects' => PilgrimageObject::query()->count(),
                'published' => PilgrimageObject::query()->where('is_published', true)->count(),
                'vicariates' => Vicariate::query()->count(),
                'deaneries' => Deanery::query()->count(),
                'sanctities' => Sanctity::query()->count(),
                'media' => ObjectMedia::query()->count(),
            ],
            'moduleStats' => [
                'routes' => PilgrimageRoute::query()->count(),
                'trips' => Trip::query()->count(),
                'bookings' => Booking::query()->count(),
                'achievements' => Achievement::query()->where('is_active', true)->count(),
                'visits_pending' => Visit::query()->where('status', 'pending')->count(),
                'reviews_pending' => Review::query()->where('status', 'pending')->count(),
                'posts_pending' => BlogPost::query()->where('status', 'pending')->count(),
                'media_pending' => UserMedia::query()->where('status', 'pending')->count(),
                'users' => User::query()->count(),
            ],
            'recentObjects' => PilgrimageObject::query()
                ->with(['objectType', 'vicariate'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
