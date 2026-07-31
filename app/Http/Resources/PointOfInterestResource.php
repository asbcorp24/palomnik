<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PointOfInterestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'icon' => $this->category_icon,
            'marker_color' => $this->marker_color,
            'name' => $this->name,
            'description' => $this->description,
            'location' => [
                'address' => $this->address,
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ],
            'contacts' => [
                'phone' => $this->phone,
                'website' => $this->website,
            ],
            'schedule' => $this->schedule_text,
            'base_object' => $this->whenLoaded('pilgrimageObject', function () {
                return $this->pilgrimageObject ? [
                    'id' => $this->pilgrimageObject->id,
                    'slug' => $this->pilgrimageObject->slug,
                    'name' => $this->pilgrimageObject->name,
                    'url' => route('objects.show', $this->pilgrimageObject),
                ] : null;
            }),
            'sort_order' => $this->sort_order,
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
