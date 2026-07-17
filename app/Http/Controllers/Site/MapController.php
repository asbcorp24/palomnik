<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __invoke(): View
    {
        $objects = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'coverMedia'])
            ->orderBy('name')
            ->get()
            ->map(function (PilgrimageObject $object) {
                return [
                    'id' => $object->id,
                    'name' => $object->name,
                    'type' => optional($object->objectType)->name,
                    'marker_color' => optional($object->objectType)->marker_color ?: '#b08a3e',
                    'address' => $object->address,
                    'latitude' => (float) $object->latitude,
                    'longitude' => (float) $object->longitude,
                    'cover' => optional($object->coverMedia)->url,
                    'url' => route('objects.show', $object),
                ];
            })
            ->values();

        return view('site.map', compact('objects'));
    }
}
