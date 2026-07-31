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
            'parent_object' => $this->whenLoaded('parentObject', function () {
                return $this->parentObject ? [
                    'id' => $this->parentObject->id,
                    'slug' => $this->parentObject->slug,
                    'name' => $this->parentObject->name,
                    'type' => $this->parentObject->objectType ? [
                        'id' => $this->parentObject->objectType->id,
                        'name' => $this->parentObject->objectType->name,
                        'slug' => $this->parentObject->objectType->slug,
                    ] : null,
                ] : null;
            }),
            'child_objects' => $this->whenLoaded('publishedChildObjects', function () {
                return $this->publishedChildObjects->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'slug' => $child->slug,
                        'name' => $child->name,
                        'type' => $child->objectType ? [
                            'id' => $child->objectType->id,
                            'name' => $child->objectType->name,
                            'slug' => $child->objectType->slug,
                        ] : null,
                    ];
                })->values();
            }),
            'child_objects_count' => $this->when(
                array_key_exists('published_child_objects_count', $this->getAttributes()),
                (int) ($this->published_child_objects_count ?? 0)
            ),
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
                        'description' => $sanctity->description,
                        'image_url' => $sanctity->image_url,
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
            'points_of_interest' => PointOfInterestResource::collection(
                $this->whenLoaded('publishedPointsOfInterest')
            ),
            'published_at' => optional($this->published_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
