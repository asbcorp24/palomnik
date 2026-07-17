<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\JointPilgrimage;
use App\Models\JointPilgrimageMember;
use App\Models\PilgrimageObject;
use App\Models\PushDevice;
use App\Models\Trip;
use App\Models\UserMedia;
use App\Models\UserRoutePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileActionController extends Controller
{
    public function storeBooking(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'participants_count' => ['required', 'integer', 'min:1', 'max:10'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = DB::transaction(function () use ($request, $trip, $data) {
            $lockedTrip = Trip::query()->with('pilgrimageRoute')->lockForUpdate()->findOrFail($trip->id);
            if ($lockedTrip->status !== 'open') {
                throw ValidationException::withMessages(['trip' => 'Запись на эту поездку закрыта.']);
            }
            if ($lockedTrip->starts_at->isPast()) {
                throw ValidationException::withMessages(['trip' => 'Дата поездки уже прошла.']);
            }

            $active = Booking::query()
                ->where('trip_id', $lockedTrip->id)
                ->where('user_id', $request->user()->id)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->exists();
            if ($active) {
                throw ValidationException::withMessages(['trip' => 'У вас уже есть активное бронирование на эту поездку.']);
            }

            $participants = (int) $data['participants_count'];
            if ($lockedTrip->capacity !== null && $lockedTrip->booked_count + $participants > $lockedTrip->capacity) {
                throw ValidationException::withMessages(['participants_count' => 'Недостаточно свободных мест.']);
            }

            $price = $lockedTrip->price !== null
                ? (float) $lockedTrip->price
                : (float) ($lockedTrip->pilgrimageRoute->base_price ?? 0);

            $booking = Booking::query()->create([
                'trip_id' => $lockedTrip->id,
                'user_id' => $request->user()->id,
                'contact_name' => $data['contact_name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
                'participants_count' => $participants,
                'total_amount' => $price * $participants,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'ticket_code' => $this->ticketCode(),
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedTrip->increment('booked_count', $participants);

            return $booking->load('trip.pilgrimageRoute');
        });

        return response()->json(['data' => $this->bookingData($booking)], 201);
    }

    public function cancelBooking(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        if (in_array($booking->status, ['cancelled', 'completed', 'refunded'], true)) {
            throw ValidationException::withMessages(['booking' => 'Бронирование уже закрыто.']);
        }

        DB::transaction(function () use ($booking) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            if ($trip->starts_at->isPast()) {
                throw ValidationException::withMessages(['booking' => 'Нельзя отменить прошедшую поездку.']);
            }
            $booking->update(['status' => 'cancelled']);
            $trip->booked_count = max(0, $trip->booked_count - $booking->participants_count);
            $trip->save();
        });

        return response()->json(['status' => 'cancelled']);
    }

    public function myJointPilgrimages(Request $request): JsonResponse
    {
        $organized = $request->user()->organizedJointPilgrimages()
            ->with(['organizer', 'pilgrimageRoute'])
            ->withCount(['members as approved_members_count' => fn ($query) => $query->where('status', 'approved')])
            ->latest('starts_at')
            ->get();

        $memberships = $request->user()->jointPilgrimageMemberships()
            ->with(['jointPilgrimage.organizer', 'jointPilgrimage.pilgrimageRoute'])
            ->latest()
            ->get();

        return response()->json([
            'organized' => $organized->map(fn ($item) => $this->jointData($item)),
            'memberships' => $memberships->map(fn ($member) => [
                'id' => $member->id,
                'status' => $member->status,
                'message' => $member->message,
                'joint' => $member->jointPilgrimage ? $this->jointData($member->jointPilgrimage) : null,
            ]),
        ]);
    }

    public function updateJointMember(Request $request, JointPilgrimage $jointPilgrimage, JointPilgrimageMember $member): JsonResponse
    {
        abort_unless($jointPilgrimage->organizer_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($member->joint_pilgrimage_id === $jointPilgrimage->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        if ($data['status'] === 'approved') {
            $approved = $jointPilgrimage->members()->where('status', 'approved')->count() + 1;
            if ($jointPilgrimage->max_participants !== null && $approved >= $jointPilgrimage->max_participants) {
                throw ValidationException::withMessages(['status' => 'Свободных мест больше нет.']);
            }
        }

        $member->update([
            'status' => $data['status'],
            'joined_at' => $data['status'] === 'approved' ? now() : null,
            'responded_at' => now(),
        ]);

        $jointPilgrimage->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['status'] === 'approved'
                ? $member->user->name.' принят в группу.'
                : 'Заявка пользователя '.$member->user->name.' отклонена.',
            'is_system' => true,
        ]);

        return response()->json(['status' => $member->status]);
    }

    public function media(Request $request): JsonResponse
    {
        $items = $request->user()->media()
            ->with('pilgrimageObject:id,name,slug')
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => collect($items->items())->map(fn (UserMedia $media) => $this->mediaData($media))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storeMedia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm', 'max:102400'],
            'pilgrimage_object_id' => ['nullable', 'integer', 'exists:pilgrimage_objects,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $file = $request->file('file');
        $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $path = $file->store('community/'.now()->format('Y/m'), 'public');

        $media = UserMedia::query()->create([
            'user_id' => $request->user()->id,
            'pilgrimage_object_id' => $data['pilgrimage_object_id'] ?? null,
            'type' => $type,
            'path' => $path,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->mediaData($media)], 201);
    }

    public function destroyMedia(Request $request, UserMedia $media): JsonResponse
    {
        abort_unless($media->user_id === $request->user()->id, 403);
        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();

        return response()->json(['deleted' => true]);
    }

    public function routePlan(Request $request, UserRoutePlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);
        $plan->load(['objects.objectType', 'objects.coverMedia']);

        return response()->json(['data' => $this->routePlanData($plan)]);
    }

    public function updateRoutePlan(Request $request, UserRoutePlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);
        $data = $this->validatedPlan($request);

        DB::transaction(function () use ($plan, $data) {
            $plan->update([
                'name' => $data['name'],
                'transport_mode' => $data['transport_mode'],
                'notes' => $data['notes'] ?? null,
                'estimated_minutes' => collect($data['objects'])->sum('stay_minutes'),
            ]);
            $plan->objects()->sync($this->routeSync($data['objects']));
        });

        return response()->json(['data' => $this->routePlanData($plan->fresh()->load(['objects.objectType', 'objects.coverMedia']))]);
    }

    public function destroyRoutePlan(Request $request, UserRoutePlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);
        $plan->delete();

        return response()->json(['deleted' => true]);
    }

    public function storePushDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $device = PushDevice::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['data' => ['id' => $device->id]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyPushDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $request->user()->pushDevices()->where('token', $data['token'])->delete();

        return response()->json(['deleted' => true]);
    }

    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'transport_mode' => ['required', Rule::in(['pedestrian', 'auto', 'masstransit'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'objects' => ['required', 'array', 'min:1', 'max:50'],
            'objects.*.id' => ['required', 'integer', 'exists:pilgrimage_objects,id'],
            'objects.*.stay_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);
    }

    private function routeSync(array $objects): array
    {
        $sync = [];
        foreach ($objects as $index => $object) {
            $sync[(int) $object['id']] = [
                'sort_order' => $index + 1,
                'stay_minutes' => (int) ($object['stay_minutes'] ?? 30),
            ];
        }

        return $sync;
    }

    private function routePlanData(UserRoutePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'transport_mode' => $plan->transport_mode,
            'estimated_minutes' => $plan->estimated_minutes,
            'notes' => $plan->notes,
            'objects' => $plan->objects->map(fn (PilgrimageObject $object) => [
                'id' => $object->id,
                'slug' => $object->slug,
                'name' => $object->name,
                'address' => $object->address,
                'latitude' => $object->latitude !== null ? (float) $object->latitude : null,
                'longitude' => $object->longitude !== null ? (float) $object->longitude : null,
                'cover_url' => optional($object->coverMedia)->url,
                'stay_minutes' => (int) ($object->pivot->stay_minutes ?? 30),
                'sort_order' => (int) ($object->pivot->sort_order ?? 0),
            ])->values(),
        ];
    }

    private function mediaData(UserMedia $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->type,
            'url' => $media->url,
            'title' => $media->title,
            'description' => $media->description,
            'latitude' => $media->latitude !== null ? (float) $media->latitude : null,
            'longitude' => $media->longitude !== null ? (float) $media->longitude : null,
            'status' => $media->status,
            'object' => $media->pilgrimageObject ? [
                'id' => $media->pilgrimageObject->id,
                'name' => $media->pilgrimageObject->name,
                'slug' => $media->pilgrimageObject->slug,
            ] : null,
            'created_at' => optional($media->created_at)->toIso8601String(),
        ];
    }

    private function bookingData(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'participants_count' => $booking->participants_count,
            'total_amount' => (float) $booking->total_amount,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'ticket_code' => $booking->ticket_code,
            'qr_payload' => $booking->ticket_token ? 'MP-TICKET:'.$booking->ticket_token : null,
            'trip' => $booking->trip ? [
                'id' => $booking->trip->id,
                'title' => $booking->trip->title,
                'starts_at' => optional($booking->trip->starts_at)->toIso8601String(),
                'meeting_point' => $booking->trip->meeting_point,
                'route_title' => optional($booking->trip->pilgrimageRoute)->title,
            ] : null,
        ];
    }

    private function jointData(JointPilgrimage $item): array
    {
        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'description' => $item->description,
            'starts_at' => optional($item->starts_at)->toIso8601String(),
            'ends_at' => optional($item->ends_at)->toIso8601String(),
            'meeting_place' => $item->meeting_place,
            'status' => $item->status,
            'max_participants' => $item->max_participants,
            'participants_count' => $item->approvedParticipantsCount(),
            'transport_mode' => $item->transport_mode,
            'join_mode' => $item->join_mode,
            'organizer' => $item->organizer ? [
                'id' => $item->organizer->id,
                'name' => $item->organizer->name,
                'avatar_url' => $item->organizer->avatar_url,
            ] : null,
        ];
    }

    private function ticketCode(): string
    {
        do {
            $code = 'MP-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Booking::query()->where('ticket_code', $code)->exists());

        return $code;
    }
}
