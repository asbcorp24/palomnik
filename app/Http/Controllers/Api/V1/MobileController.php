<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\FavoriteList;
use App\Models\JointPilgrimage;
use App\Models\JointPilgrimageMember;
use App\Models\PilgrimageObject;
use App\Models\PilgrimageRoute;
use App\Models\Review;
use App\Models\Trip;
use App\Models\UserRoutePlan;
use App\Models\Visit;
use App\Services\AchievementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    public function home(): JsonResponse
    {
        $objects = PilgrimageObject::query()
            ->published()
            ->with(['objectType', 'coverMedia', 'sanctities'])
            ->latest('published_at')
            ->limit(6)
            ->get();

        $routes = PilgrimageRoute::query()
            ->published()
            ->withCount('objects')
            ->with(['trips' => fn ($query) => $query->where('status', 'open')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(2)])
            ->latest('published_at')
            ->limit(5)
            ->get();

        $events = CalendarEvent::query()
            ->published()
            ->upcoming()
            ->with('pilgrimageObject')
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        return response()->json([
            'objects' => $objects->map(fn ($object) => $this->objectData($object)),
            'routes' => $routes->map(fn ($route) => $this->routeData($route)),
            'events' => $events->map(fn ($event) => $this->eventData($event)),
        ]);
    }

    public function routes(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'difficulty' => ['nullable', 'string', 'max:64'],
        ]);

        $routes = PilgrimageRoute::query()
            ->published()
            ->withCount('objects')
            ->with(['trips' => fn ($query) => $query->where('starts_at', '>=', now())->whereIn('status', ['planned', 'open'])->orderBy('starts_at')])
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term) {
                $query->where('title', 'like', '%'.trim($term).'%')
                    ->orWhere('short_description', 'like', '%'.trim($term).'%');
            }))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['difficulty'] ?? null, fn (Builder $query, string $value) => $query->where('difficulty', $value))
            ->latest('published_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($routes->items())->map(fn ($route) => $this->routeData($route))->values(),
            'meta' => $this->paginationMeta($routes),
        ]);
    }

    public function route(PilgrimageRoute $pilgrimageRoute): JsonResponse
    {
        abort_unless($pilgrimageRoute->is_published, 404);
        $pilgrimageRoute->load([
            'objects.objectType',
            'objects.coverMedia',
            'trips' => fn ($query) => $query->where('starts_at', '>=', now())->whereIn('status', ['planned', 'open'])->orderBy('starts_at'),
        ])->loadCount('objects');

        return response()->json(['data' => $this->routeData($pilgrimageRoute, true)]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(array_keys(CalendarEvent::typeLabels()))],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->addMonths(2)->endOfMonth()->toDateString();

        $events = CalendarEvent::query()
            ->published()
            ->with(['pilgrimageObject', 'pilgrimageRoute', 'trip'])
            ->where(function (Builder $query) use ($from, $to) {
                $query->whereBetween('starts_at', [$from, $to.' 23:59:59'])
                    ->orWhereBetween('ends_at', [$from, $to.' 23:59:59'])
                    ->orWhere(fn (Builder $query) => $query->where('starts_at', '<=', $from)->where('ends_at', '>=', $to));
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $value) => $query->where('type', $value))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term) {
                $query->where('title', 'like', '%'.trim($term).'%')
                    ->orWhere('location', 'like', '%'.trim($term).'%')
                    ->orWhere('address', 'like', '%'.trim($term).'%');
            }))
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $events->map(fn ($event) => $this->eventData($event)),
            'types' => CalendarEvent::typeLabels(),
        ]);
    }

    public function event(CalendarEvent $calendarEvent): JsonResponse
    {
        abort_unless($calendarEvent->is_published, 404);
        $calendarEvent->load(['pilgrimageObject', 'pilgrimageRoute', 'trip']);

        return response()->json(['data' => $this->eventData($calendarEvent, true)]);
    }

    public function community(): JsonResponse
    {
        $posts = BlogPost::query()
            ->where('status', 'published')
            ->with(['user', 'media' => fn ($query) => $query->where('status', 'published')])
            ->latest('published_at')
            ->paginate(15);

        return response()->json([
            'data' => collect($posts->items())->map(fn ($post) => $this->postData($post))->values(),
            'meta' => $this->paginationMeta($posts),
        ]);
    }

    public function post(BlogPost $post): JsonResponse
    {
        abort_unless($post->status === 'published', 404);
        $post->load(['user', 'media' => fn ($query) => $query->where('status', 'published')]);

        return response()->json(['data' => $this->postData($post, true)]);
    }

    public function together(Request $request): JsonResponse
    {
        $items = JointPilgrimage::query()
            ->published()
            ->upcoming()
            ->with(['organizer', 'pilgrimageRoute'])
            ->withCount(['members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved')])
            ->when($request->query('q'), fn (Builder $query, string $term) => $query->where(function (Builder $query) use ($term) {
                $query->where('title', 'like', '%'.trim($term).'%')
                    ->orWhere('meeting_place', 'like', '%'.trim($term).'%');
            }))
            ->orderBy('starts_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn ($item) => $this->jointData($item))->values(),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function joint(Request $request, JointPilgrimage $jointPilgrimage): JsonResponse
    {
        $user = $request->user();
        $membership = $user ? $jointPilgrimage->members()->where('user_id', $user->id)->first() : null;
        $canSee = $jointPilgrimage->status === 'published'
            || ($user && ($user->id === $jointPilgrimage->organizer_id || $user->isAdmin() || $membership));
        abort_unless($canSee, 404);

        $jointPilgrimage->load(['organizer', 'pilgrimageRoute.objects.objectType', 'members.user'])
            ->loadCount(['members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved')]);

        $canDiscuss = $user && ($user->id === $jointPilgrimage->organizer_id || $user->isAdmin() || optional($membership)->status === 'approved');
        $messages = $canDiscuss
            ? $jointPilgrimage->messages()->with('user')->latest()->limit(200)->get()->reverse()->values()
            : collect();

        $data = $this->jointData($jointPilgrimage, true);
        $data['membership_status'] = optional($membership)->status;
        $data['can_manage'] = $user && ($user->id === $jointPilgrimage->organizer_id || $user->isAdmin());
        $data['can_discuss'] = (bool) $canDiscuss;
        $data['messages'] = $messages->map(fn ($message) => [
            'id' => $message->id,
            'body' => $message->body,
            'is_system' => (bool) $message->is_system,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'user' => $message->user ? $this->publicUserData($message->user) : null,
        ]);

        return response()->json(['data' => $data]);
    }

    public function profile(Request $request, AchievementService $achievementService): JsonResponse
    {
        $achievementService->evaluate($request->user());
        $user = $request->user()->loadCount([
            'bookings', 'visits', 'reviews', 'blogPosts', 'media', 'favoriteLists', 'achievements',
        ]);

        return response()->json([
            'user' => $this->privateUserData($user),
            'stats' => [
                'bookings' => $user->bookings_count,
                'visits' => $user->visits_count,
                'reviews' => $user->reviews_count,
                'posts' => $user->blog_posts_count,
                'media' => $user->media_count,
                'favorite_lists' => $user->favorite_lists_count,
                'achievements' => $user->achievements_count,
            ],
            'achievements' => $user->achievements()->get()->map(fn ($achievement) => $this->achievementData($achievement, true)),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:64', Rule::unique('users', 'phone')->ignore($user->id)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'notifications' => ['nullable', 'boolean'],
            'privacy' => ['required', Rule::in(['private', 'registered', 'public'])],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'font_size' => ['required', Rule::in(['normal', 'large', 'extra_large'])],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:64'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?: null,
            'birth_date' => $data['birth_date'] ?: null,
            'preferences' => [
                'notifications' => $request->boolean('notifications'),
                'privacy' => $data['privacy'],
                'theme' => $data['theme'],
                'font_size' => $data['font_size'],
                'interests' => array_values($data['interests'] ?? []),
            ],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['user' => $this->privateUserData($user->fresh())]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $lists = $request->user()->favoriteLists()
            ->with(['objects.objectType', 'objects.coverMedia', 'objects.sanctities'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($lists->isEmpty()) {
            $list = FavoriteList::query()->create([
                'user_id' => $request->user()->id,
                'name' => 'Избранное',
                'is_default' => true,
            ]);
            $lists = collect([$list->load('objects')]);
        }

        return response()->json(['data' => $lists->map(fn ($list) => [
            'id' => $list->id,
            'name' => $list->name,
            'is_default' => (bool) $list->is_default,
            'objects' => $list->objects->map(fn ($object) => $this->objectData($object)),
        ])]);
    }

    public function toggleFavorite(Request $request, PilgrimageObject $pilgrimageObject): JsonResponse
    {
        $list = $request->user()->favoriteLists()->where('is_default', true)->first()
            ?: $request->user()->favoriteLists()->firstOrCreate(['name' => 'Избранное'], ['is_default' => true]);
        $attached = $list->objects()->whereKey($pilgrimageObject->id)->exists();

        if ($attached) {
            $list->objects()->detach($pilgrimageObject->id);
        } else {
            $list->objects()->attach($pilgrimageObject->id);
        }

        return response()->json(['is_favorite' => ! $attached]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()
            ->with(['trip.pilgrimageRoute', 'checkedInBy'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($bookings->items())->map(fn ($booking) => $this->bookingData($booking))->values(),
            'meta' => $this->paginationMeta($bookings),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()->paginate(30);

        return response()->json([
            'data' => collect($items->items())->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => optional($notification->read_at)->toIso8601String(),
                'created_at' => optional($notification->created_at)->toIso8601String(),
            ])->values(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'meta' => $this->paginationMeta($items),
        ]);
    }

    public function readNotification(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['read' => true]);
    }

    public function storeVisit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pilgrimage_object_id' => ['required', 'integer', 'exists:pilgrimage_objects,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $visit = Visit::query()->create([
            'user_id' => $request->user()->id,
            'pilgrimage_object_id' => $data['pilgrimage_object_id'],
            'visited_at' => now(),
            'verification_method' => isset($data['latitude'], $data['longitude']) ? 'geolocation' : 'manual',
            'status' => 'pending',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $visit], 201);
    }

    public function storeReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pilgrimage_object_id' => ['required', 'integer', 'exists:pilgrimage_objects,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $review = Review::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'pilgrimage_object_id' => $data['pilgrimage_object_id']],
            ['rating' => $data['rating'], 'body' => $data['body'], 'status' => 'pending', 'moderated_by' => null, 'moderated_at' => null]
        );

        return response()->json(['data' => $review], $review->wasRecentlyCreated ? 201 : 200);
    }

    public function createJoint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'meeting_place' => ['required', 'string', 'max:500'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:200'],
            'transport_mode' => ['required', Rule::in(['walk', 'public', 'car', 'bus', 'mixed'])],
            'join_mode' => ['required', Rule::in(['approval', 'auto'])],
            'contact_method' => ['nullable', Rule::in(['in_app', 'phone', 'telegram', 'whatsapp'])],
            'contact_value' => ['nullable', 'string', 'max:255'],
        ]);

        $base = Str::slug($data['title']) ?: 'pilgrimage-together';
        $slug = $base;
        $index = 2;
        while (JointPilgrimage::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        $item = $request->user()->organizedJointPilgrimages()->create([
            ...$data,
            'slug' => $slug,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->jointData($item->load(['organizer', 'pilgrimageRoute']))], 201);
    }

    public function joinJoint(Request $request, JointPilgrimage $jointPilgrimage): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1500']]);
        abort_if($jointPilgrimage->organizer_id === $request->user()->id, 422, 'Организатор уже участвует.');

        $member = DB::transaction(function () use ($request, $jointPilgrimage, $data) {
            $item = JointPilgrimage::query()->lockForUpdate()->findOrFail($jointPilgrimage->id);
            abort_unless($item->status === 'published', 422, 'Набор участников недоступен.');
            abort_if($item->starts_at->isPast(), 422, 'Дата паломничества уже прошла.');
            $approved = $item->members()->where('status', 'approved')->count() + 1;
            abort_if($item->max_participants !== null && $approved >= $item->max_participants, 422, 'Свободных мест нет.');
            $status = $item->join_mode === 'auto' ? 'approved' : 'pending';

            return JointPilgrimageMember::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $item->id, 'user_id' => $request->user()->id],
                ['status' => $status, 'message' => $data['message'] ?? null, 'joined_at' => $status === 'approved' ? now() : null, 'responded_at' => $status === 'approved' ? now() : null]
            );
        });

        return response()->json(['status' => $member->status]);
    }

    public function leaveJoint(Request $request, JointPilgrimage $jointPilgrimage): JsonResponse
    {
        $member = $jointPilgrimage->members()->where('user_id', $request->user()->id)->firstOrFail();
        $member->update(['status' => 'left', 'responded_at' => now()]);

        return response()->json(['status' => 'left']);
    }

    public function storeJointMessage(Request $request, JointPilgrimage $jointPilgrimage): JsonResponse
    {
        $member = $jointPilgrimage->members()->where('user_id', $request->user()->id)->first();
        $canDiscuss = $jointPilgrimage->organizer_id === $request->user()->id
            || $request->user()->isAdmin()
            || optional($member)->status === 'approved';
        abort_unless($canDiscuss, 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        $message = $jointPilgrimage->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_system' => false,
        ])->load('user');

        return response()->json(['data' => [
            'id' => $message->id,
            'body' => $message->body,
            'is_system' => false,
            'created_at' => $message->created_at->toIso8601String(),
            'user' => $this->publicUserData($message->user),
        ]], 201);
    }

    public function routePlans(Request $request): JsonResponse
    {
        $plans = $request->user()->routePlans()->with(['objects.objectType', 'objects.coverMedia'])->latest()->get();

        return response()->json(['data' => $plans->map(fn ($plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'transport_mode' => $plan->transport_mode,
            'estimated_minutes' => $plan->estimated_minutes,
            'notes' => $plan->notes,
            'objects' => $plan->objects->map(fn ($object) => $this->objectData($object)),
        ])]);
    }

    public function storeRoutePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'transport_mode' => ['required', Rule::in(['pedestrian', 'auto', 'masstransit'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'object_ids' => ['required', 'array', 'min:1', 'max:50'],
            'object_ids.*' => ['integer', 'exists:pilgrimage_objects,id'],
        ]);

        $plan = DB::transaction(function () use ($request, $data) {
            $plan = UserRoutePlan::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'transport_mode' => $data['transport_mode'],
                'notes' => $data['notes'] ?? null,
            ]);
            $sync = [];
            foreach (array_values(array_unique($data['object_ids'])) as $index => $objectId) {
                $sync[$objectId] = ['sort_order' => $index + 1, 'stay_minutes' => 30];
            }
            $plan->objects()->sync($sync);

            return $plan->load(['objects.objectType', 'objects.coverMedia']);
        });

        return response()->json(['data' => [
            'id' => $plan->id,
            'name' => $plan->name,
            'transport_mode' => $plan->transport_mode,
            'objects' => $plan->objects->map(fn ($object) => $this->objectData($object)),
        ]], 201);
    }

    private function objectData(PilgrimageObject $object): array
    {
        return [
            'id' => $object->id,
            'slug' => $object->slug,
            'name' => $object->name,
            'type' => $object->relationLoaded('objectType') && $object->objectType ? [
                'name' => $object->objectType->name,
                'slug' => $object->objectType->slug,
                'marker_color' => $object->objectType->marker_color,
            ] : null,
            'short_description' => $object->short_description,
            'address' => $object->address,
            'latitude' => $object->latitude !== null ? (float) $object->latitude : null,
            'longitude' => $object->longitude !== null ? (float) $object->longitude : null,
            'cover_url' => $object->relationLoaded('coverMedia') ? optional($object->coverMedia)->url : null,
            'sanctities' => $object->relationLoaded('sanctities') ? $object->sanctities->pluck('name')->values() : [],
        ];
    }

    private function routeData(PilgrimageRoute $route, bool $full = false): array
    {
        $data = [
            'id' => $route->id,
            'slug' => $route->slug,
            'title' => $route->title,
            'category' => $route->category,
            'difficulty' => $route->difficulty,
            'duration_days' => $route->duration_days,
            'duration_minutes' => $route->duration_minutes,
            'short_description' => $route->short_description,
            'base_price' => $route->base_price !== null ? (float) $route->base_price : null,
            'cover_url' => $route->cover_url,
            'objects_count' => $route->objects_count ?? ($route->relationLoaded('objects') ? $route->objects->count() : null),
            'trips' => $route->relationLoaded('trips') ? $route->trips->map(fn (Trip $trip) => $this->tripData($trip)) : [],
        ];
        if ($full) {
            $data['description'] = $route->description;
            $data['program'] = $route->program;
            $data['objects'] = $route->objects->map(fn ($object) => $this->objectData($object));
        }

        return $data;
    }

    private function tripData(Trip $trip): array
    {
        return [
            'id' => $trip->id,
            'title' => $trip->title,
            'starts_at' => optional($trip->starts_at)->toIso8601String(),
            'ends_at' => optional($trip->ends_at)->toIso8601String(),
            'meeting_point' => $trip->meeting_point,
            'capacity' => $trip->capacity,
            'booked_count' => $trip->booked_count,
            'price' => $trip->price !== null ? (float) $trip->price : null,
            'status' => $trip->status,
        ];
    }

    private function eventData(CalendarEvent $event, bool $full = false): array
    {
        $data = [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'type' => $event->type,
            'type_label' => CalendarEvent::typeLabels()[$event->type] ?? $event->type,
            'short_description' => $event->short_description,
            'starts_at' => optional($event->starts_at)->toIso8601String(),
            'ends_at' => optional($event->ends_at)->toIso8601String(),
            'all_day' => (bool) $event->all_day,
            'location' => $event->location,
            'address' => $event->address,
            'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            'object' => $event->relationLoaded('pilgrimageObject') && $event->pilgrimageObject ? $this->objectData($event->pilgrimageObject) : null,
        ];
        if ($full) {
            $data['description'] = $event->description;
            $data['capacity'] = $event->capacity;
            $data['registration_url'] = $event->registration_url;
            $data['contact_phone'] = $event->contact_phone;
            $data['contact_email'] = $event->contact_email;
            $data['route'] = $event->pilgrimageRoute ? $this->routeData($event->pilgrimageRoute) : null;
            $data['trip'] = $event->trip ? $this->tripData($event->trip) : null;
            $data['ics_url'] = route('calendar.ics', $event);
        }

        return $data;
    }

    private function postData(BlogPost $post, bool $full = false): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'body' => $full ? $post->body : null,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'author' => $post->user ? $this->publicUserData($post->user) : null,
            'media' => $post->relationLoaded('media') ? $post->media->map(fn ($media) => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => $media->url,
                'title' => $media->title,
            ]) : [],
        ];
    }

    private function jointData(JointPilgrimage $item, bool $full = false): array
    {
        $data = [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'description' => $full ? $item->description : Str::limit($item->description, 240),
            'starts_at' => optional($item->starts_at)->toIso8601String(),
            'ends_at' => optional($item->ends_at)->toIso8601String(),
            'meeting_place' => $item->meeting_place,
            'max_participants' => $item->max_participants,
            'participants_count' => $item->approvedParticipantsCount(),
            'available_places' => $item->availablePlaces(),
            'transport_mode' => $item->transport_mode,
            'join_mode' => $item->join_mode,
            'status' => $item->status,
            'organizer' => $item->organizer ? $this->publicUserData($item->organizer) : null,
            'route' => $item->pilgrimageRoute ? $this->routeData($item->pilgrimageRoute) : null,
        ];
        if ($full) {
            $data['contact_method'] = $item->contact_method;
            $data['contact_value'] = $item->contact_value;
            $data['members'] = $item->relationLoaded('members') ? $item->members->map(fn ($member) => [
                'id' => $member->id,
                'status' => $member->status,
                'user' => $member->user ? $this->publicUserData($member->user) : null,
            ]) : [];
        }

        return $data;
    }

    private function bookingData(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'contact_name' => $booking->contact_name,
            'participants_count' => $booking->participants_count,
            'total_amount' => (float) $booking->total_amount,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'ticket_code' => $booking->ticket_code,
            'qr_payload' => $booking->ticket_token ? 'MP-TICKET:'.$booking->ticket_token : null,
            'checked_in_at' => optional($booking->checked_in_at)->toIso8601String(),
            'checked_in_participants' => $booking->checked_in_participants,
            'ticket_url' => route('tickets.show', $booking),
            'calendar_url' => route('tickets.ics', $booking),
            'trip' => $booking->trip ? [
                ...$this->tripData($booking->trip),
                'route' => $booking->trip->pilgrimageRoute ? $this->routeData($booking->trip->pilgrimageRoute) : null,
            ] : null,
        ];
    }

    private function publicUserData($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
            'is_verified_organizer' => (bool) $user->is_verified_organizer,
        ];
    }

    private function privateUserData($user): array
    {
        return [
            ...$this->publicUserData($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'birth_date' => optional($user->birth_date)->format('Y-m-d'),
            'preferences' => $user->preferences ?: [],
        ];
    }

    private function achievementData(Achievement $achievement, bool $earned = false): array
    {
        return [
            'id' => $achievement->id,
            'slug' => $achievement->slug,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'points' => $achievement->points,
            'badge_level' => $achievement->badge_level,
            'icon' => $achievement->icon,
            'earned' => $earned,
            'awarded_at' => $earned ? optional($achievement->pivot->awarded_at)->toIso8601String() : null,
            'progress' => $earned ? $achievement->pivot->progress : null,
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
