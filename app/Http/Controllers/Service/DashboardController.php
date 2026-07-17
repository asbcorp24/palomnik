<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $assignments = $user->objectRepresentatives()
            ->with(['pilgrimageObject.objectType', 'pilgrimageObject.coverMedia'])
            ->orderByDesc('verified_at')
            ->get();

        $stats = [
            'objects' => $assignments->where('status', 'approved')->count(),
            'pending_assignments' => $assignments->where('status', 'pending')->count(),
            'pending_updates' => $user->objectUpdateRequests()->where('status', 'pending')->count(),
            'pending_media' => $user->objectMediaSubmissions()->where('status', 'pending')->count(),
        ];

        $recentRequests = $user->objectUpdateRequests()
            ->with('pilgrimageObject')
            ->latest()
            ->limit(10)
            ->get();

        return view('service.dashboard', compact('assignments', 'stats', 'recentRequests'));
    }
}
