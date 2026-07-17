<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JointPilgrimage;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileTogetherController extends Controller
{
    public function show(Request $request, JointPilgrimage $jointPilgrimage): JsonResponse
    {
        $user = $request->user();
        $membership = $user
            ? $jointPilgrimage->members()->where('user_id', $user->id)->first()
            : null;

        $canManage = $user && ($user->id === $jointPilgrimage->organizer_id || $user->isAdmin());
        $canSee = $jointPilgrimage->status === 'published' || $canManage || $membership;
        abort_unless($canSee, 404);

        $jointPilgrimage->load([
            'organizer',
            'pilgrimageRoute.objects.objectType',
            'members.user',
        ])->loadCount([
            'members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved'),
        ]);

        $canDiscuss = $user && ($canManage || optional($membership)->status === 'approved');
        $messages = $canDiscuss
            ? $jointPilgrimage->messages()->with('user')->latest()->limit(200)->get()->reverse()->values()
            : collect();

        return response()->json(['data' => [
            'id' => $jointPilgrimage->id,
            'slug' => $jointPilgrimage->slug,
            'title' => $jointPilgrimage->title,
            'description' => $jointPilgrimage->description,
            'starts_at' => optional($jointPilgrimage->starts_at)->toIso8601String(),
            'ends_at' => optional($jointPilgrimage->ends_at)->toIso8601String(),
            'meeting_place' => $jointPilgrimage->meeting_place,
            'max_participants' => $jointPilgrimage->max_participants,
            'participants_count' => $jointPilgrimage->approvedParticipantsCount(),
            'available_places' => $jointPilgrimage->availablePlaces(),
            'transport_mode' => $jointPilgrimage->transport_mode,
            'join_mode' => $jointPilgrimage->join_mode,
            'status' => $jointPilgrimage->status,
            'organizer' => $this->userData($jointPilgrimage->organizer),
            'route' => $jointPilgrimage->pilgrimageRoute
                ? $this->routeData($jointPilgrimage->pilgrimageRoute)
                : null,
            'membership_status' => optional($membership)->status,
            'can_manage' => (bool) $canManage,
            'can_discuss' => (bool) $canDiscuss,
            'contact_method' => $canDiscuss ? $jointPilgrimage->contact_method : null,
            'contact_value' => $canDiscuss ? $jointPilgrimage->contact_value : null,
            'members' => $canManage
                ? $jointPilgrimage->members->map(fn ($member) => [
                    'id' => $member->id,
                    'status' => $member->status,
                    'message' => $member->message,
                    'user' => $this->userData($member->user),
                ])->values()
                : [],
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'is_system' => (bool) $message->is_system,
                'created_at' => optional($message->created_at)->toIso8601String(),
                'user' => $message->user ? $this->userData($message->user) : null,
            ])->values(),
        ]]);
    }

    private function routeData(PilgrimageRoute $route): array
    {
        return [
            'id' => $route->id,
            'slug' => $route->slug,
            'title' => $route->title,
            'category' => $route->category,
            'difficulty' => $route->difficulty,
            'duration_days' => $route->duration_days,
            'short_description' => $route->short_description,
            'cover_url' => $route->cover_url,
            'objects' => $route->relationLoaded('objects')
                ? $route->objects->map(fn (PilgrimageObject $object) => [
                    'id' => $object->id,
                    'slug' => $object->slug,
                    'name' => $object->name,
                    'address' => $object->address,
                    'latitude' => $object->latitude !== null ? (float) $object->latitude : null,
                    'longitude' => $object->longitude !== null ? (float) $object->longitude : null,
                ])->values()
                : [],
            'trips' => $route->relationLoaded('trips')
                ? $route->trips->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'starts_at' => optional($trip->starts_at)->toIso8601String(),
                    'status' => $trip->status,
                ])->values()
                : [],
        ];
    }

    private function userData($user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'is_verified_organizer' => (bool) $user->is_verified_organizer,
        ];
    }
}
