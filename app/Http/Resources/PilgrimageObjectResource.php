<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PilgrimageObjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->whenLoaded('objectType', function () {
                return [
                    'id' => $this->objectType->id,
                    'name' => $this->objectType->name,
                    'slug' => $this->objectType->slug,
                    'marker_color' => $this->objectType->marker_color,
                    'icon' => $this->objectType->icon,
                ];
            }),
            'vicariate' => $this->whenLoaded('vicariate', function () {
                return $this->vicariate ? [
                    'id' => $this->vicariate->id,
                    'name' => $this->vicariate->name,
                    'slug' => $this->vicariate->slug,
                ] : null;
            }),
            'deanery' => $this->whenLoaded('deanery', function () {
                return $this->deanery ? [
                    'id' => $this->deanery->id,
                    'name' => $this->deanery->name,
                    'slug' => $this->deanery->slug,
                ] : null;
            }),
            'short_description' => $this->short_description,
            'description' => $this->when($request->routeIs('api.v1.objects.show'), $this->description),
            'history' => $this->when($request->routeIs('api.v1.objects.show'), $this->history),
            'location' => [
                'address' => $this->address,
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ],
            'contacts' => [
                'phone' => $this->phone,
                'email' => $this->email,
                'website' => $this->website,
            ],
            'schedule' => $this->schedule_text,
            'amenities' => [
                'parking' => $this->parking_info,
                'accessibility' => $this->accessibility_info,
            ],
            'cover' => $this->whenLoaded('coverMedia', function () {
                return $this->coverMedia ? [
                    'url' => $this->coverMedia->url,
                    'title' => $this->coverMedia->title,
                ] : null;
            }),
            'sanctities' => $this->whenLoaded('sanctities', function () {
                return $this->sanctities->map(function ($sanctity) {
                    return [
                        'id' => $sanctity->id,
                        'name' => $sanctity->name,
                        'slug' => $sanctity->slug,
                        'type' => $sanctity->type,
                        'note' => $sanctity->pivot->note,
                    ];
                })->values();
            }),
            'media' => $this->whenLoaded('media', function () {
                return $this->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'url' => $media->url,
                        'title' => $media->title,
                        'description' => $media->description,
                    ];
                })->values();
            }),
            'published_at' => optional($this->published_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
