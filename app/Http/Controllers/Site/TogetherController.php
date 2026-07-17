<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\JointPilgrimage;
use App\Models\JointPilgrimageMember;
use App\Models\PilgrimageRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TogetherController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'transport' => ['nullable', Rule::in(array_keys($this->transportModes()))],
            'date' => ['nullable', 'date'],
        ]);

        $items = JointPilgrimage::query()
            ->published()
            ->upcoming()
            ->with(['organizer', 'pilgrimageRoute'])
            ->withCount(['members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved')])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('meeting_place', 'like', "%{$term}%")
                        ->orWhereHas('pilgrimageRoute', fn (Builder $query) => $query->where('title', 'like', "%{$term}%"));
                });
            })
            ->when($filters['transport'] ?? null, fn (Builder $query, string $mode) => $query->where('transport_mode', $mode))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('starts_at', $date))
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('site.together.index', [
            'items' => $items,
            'filters' => $filters,
            'transportModes' => $this->transportModes(),
        ]);
    }

    public function my(Request $request): View
    {
        $organized = $request->user()->organizedJointPilgrimages()
            ->withCount(['members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved')])
            ->latest('starts_at')
            ->get();

        $memberships = $request->user()->jointPilgrimageMemberships()
            ->with(['jointPilgrimage.organizer', 'jointPilgrimage.pilgrimageRoute'])
            ->latest()
            ->get();

        return view('site.together.my', compact('organized', 'memberships'));
    }

    public function create(): View
    {
        return view('site.together.form', [
            'item' => new JointPilgrimage(),
            'routes' => $this->routes(),
            'transportModes' => $this->transportModes(),
            'joinModes' => $this->joinModes(),
            'contactMethods' => $this->contactMethods(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $item = $request->user()->organizedJointPilgrimages()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => 'pending',
        ]);

        return redirect()->route('together.show', $item)
            ->with('success', 'Предложение создано и отправлено на модерацию. После публикации другие паломники смогут присоединиться.');
    }

    public function show(Request $request, JointPilgrimage $jointPilgrimage): View
    {
        $user = $request->user();
        $membership = $user
            ? $jointPilgrimage->members()->where('user_id', $user->id)->first()
            : null;

        $canManage = $user && ($jointPilgrimage->organizer_id === $user->id || $user->isAdmin());
        abort_unless($jointPilgrimage->status === 'published' || $canManage || $membership, 404);

        $jointPilgrimage->load([
            'organizer',
            'pilgrimageRoute.objects.objectType',
            'members.user',
        ])->loadCount([
            'members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved'),
        ]);

        $canDiscuss = $user && ($canManage || optional($membership)->status === 'approved');
        $messages = $canDiscuss
            ? $jointPilgrimage->messages()->with('user')->limit(200)->get()
            : collect();

        return view('site.together.show', [
            'item' => $jointPilgrimage,
            'membership' => $membership,
            'canManage' => $canManage,
            'canDiscuss' => $canDiscuss,
            'messages' => $messages,
            'transportModes' => $this->transportModes(),
            'joinModes' => $this->joinModes(),
            'contactMethods' => $this->contactMethods(),
        ]);
    }

    public function edit(Request $request, JointPilgrimage $jointPilgrimage): View
    {
        $this->authorizeManager($request, $jointPilgrimage);

        return view('site.together.form', [
            'item' => $jointPilgrimage,
            'routes' => $this->routes(),
            'transportModes' => $this->transportModes(),
            'joinModes' => $this->joinModes(),
            'contactMethods' => $this->contactMethods(),
        ]);
    }

    public function update(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $this->authorizeManager($request, $jointPilgrimage);
        $data = $this->validated($request);

        if ($jointPilgrimage->title !== $data['title']) {
            $data['slug'] = $this->uniqueSlug($data['title'], $jointPilgrimage->id);
        }

        if (! $request->user()->isAdmin()) {
            $data['status'] = 'pending';
            $data['moderated_by'] = null;
            $data['moderated_at'] = null;
        }

        $jointPilgrimage->update($data);

        return redirect()->route('together.show', $jointPilgrimage)
            ->with('success', $request->user()->isAdmin() ? 'Встреча обновлена.' : 'Изменения сохранены и повторно отправлены на модерацию.');
    }

    public function destroy(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $this->authorizeManager($request, $jointPilgrimage);
        $jointPilgrimage->delete();

        return redirect()->route('together.my')->with('success', 'Предложение удалено.');
    }

    public function join(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:1500'],
        ]);

        abort_if($jointPilgrimage->organizer_id === $request->user()->id, 422, 'Организатор уже участвует в паломничестве.');

        DB::transaction(function () use ($request, $jointPilgrimage, $data) {
            $item = JointPilgrimage::query()->lockForUpdate()->findOrFail($jointPilgrimage->id);
            abort_unless($item->status === 'published', 422, 'Набор участников пока недоступен.');
            abort_if($item->starts_at->isPast(), 422, 'Дата паломничества уже прошла.');

            $approved = $item->members()->where('status', 'approved')->count() + 1;
            abort_if($item->max_participants !== null && $approved >= $item->max_participants, 422, 'Свободных мест больше нет.');

            $status = $item->join_mode === 'auto' ? 'approved' : 'pending';
            $member = JointPilgrimageMember::query()->updateOrCreate(
                ['joint_pilgrimage_id' => $item->id, 'user_id' => $request->user()->id],
                [
                    'status' => $status,
                    'message' => $data['message'] ?? null,
                    'joined_at' => $status === 'approved' ? now() : null,
                    'responded_at' => $status === 'approved' ? now() : null,
                ]
            );

            if ($member->wasRecentlyCreated || $member->wasChanged('status')) {
                $item->messages()->create([
                    'user_id' => $request->user()->id,
                    'body' => $status === 'approved'
                        ? $request->user()->name.' присоединился к паломничеству.'
                        : $request->user()->name.' отправил заявку на участие.',
                    'is_system' => true,
                ]);
            }
        });

        $message = $jointPilgrimage->join_mode === 'auto'
            ? 'Вы присоединились. Теперь доступно обсуждение поездки.'
            : 'Заявка отправлена организатору.';

        return back()->with('success', $message);
    }

    public function leave(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $membership = $jointPilgrimage->members()->where('user_id', $request->user()->id)->firstOrFail();
        $membership->update(['status' => 'left', 'responded_at' => now()]);

        $jointPilgrimage->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->user()->name.' отказался от участия.',
            'is_system' => true,
        ]);

        return back()->with('success', 'Вы вышли из группы паломничества.');
    }

    public function updateMember(Request $request, JointPilgrimage $jointPilgrimage, JointPilgrimageMember $member): RedirectResponse
    {
        $this->authorizeManager($request, $jointPilgrimage);
        abort_unless($member->joint_pilgrimage_id === $jointPilgrimage->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        if ($data['status'] === 'approved') {
            $approved = $jointPilgrimage->members()->where('status', 'approved')->count() + 1;
            abort_if($jointPilgrimage->max_participants !== null && $approved >= $jointPilgrimage->max_participants, 422, 'Свободных мест больше нет.');
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

        return back()->with('success', 'Статус участника обновлён.');
    }

    public function storeMessage(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $membership = $jointPilgrimage->members()->where('user_id', $request->user()->id)->first();
        $canDiscuss = $jointPilgrimage->organizer_id === $request->user()->id
            || $request->user()->isAdmin()
            || optional($membership)->status === 'approved';
        abort_unless($canDiscuss, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $jointPilgrimage->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_system' => false,
        ]);

        return back()->with('success', 'Сообщение отправлено.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pilgrimage_route_id' => ['nullable', 'integer', 'exists:pilgrimage_routes,id'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'meeting_place' => ['required', 'string', 'max:500'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:200'],
            'transport_mode' => ['required', Rule::in(array_keys($this->transportModes()))],
            'join_mode' => ['required', Rule::in(array_keys($this->joinModes()))],
            'contact_method' => ['nullable', Rule::in(array_keys($this->contactMethods()))],
            'contact_value' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function authorizeManager(Request $request, JointPilgrimage $item): void
    {
        abort_unless($item->organizer_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'pilgrimage-together';
        $slug = $base;
        $index = 2;

        while (JointPilgrimage::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function routes()
    {
        return PilgrimageRoute::query()->published()->orderBy('title')->get(['id', 'title']);
    }

    private function transportModes(): array
    {
        return [
            'walk' => 'Пешком',
            'public' => 'Общественный транспорт',
            'car' => 'Автомобиль',
            'bus' => 'Заказной автобус',
            'mixed' => 'Смешанный вариант',
        ];
    }

    private function joinModes(): array
    {
        return [
            'approval' => 'По заявке организатору',
            'auto' => 'Свободное присоединение',
        ];
    }

    private function contactMethods(): array
    {
        return [
            'in_app' => 'Только обсуждение на сайте',
            'phone' => 'Телефон',
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
        ];
    }
}
