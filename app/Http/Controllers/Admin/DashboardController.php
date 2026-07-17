<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deanery;
use App\Models\ObjectMedia;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use App\Models\Vicariate;
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
            'recentObjects' => PilgrimageObject::query()
                ->with(['objectType', 'vicariate'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
