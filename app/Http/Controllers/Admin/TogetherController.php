<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JointPilgrimage;
use App\Notifications\PlatformNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TogetherController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $items = JointPilgrimage::query()
            ->with(['organizer', 'pilgrimageRoute'])
            ->withCount([
                'members',
                'members as approved_members_count' => fn (Builder $query) => $query->where('status', 'approved'),
                'members as pending_members_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'messages',
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('meeting_place', 'like', "%{$term}%")
                        ->orWhereHas('organizer', function (Builder $query) use ($term) {
                            $query->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'published' THEN 2 WHEN 'rejected' THEN 3 WHEN 'cancelled' THEN 4 WHEN 'completed' THEN 5 ELSE 6 END")
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.together.index', [
            'items' => $items,
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'moderation_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $jointPilgrimage->update([
            'status' => $data['status'],
            'moderation_note' => $data['moderation_note'] ?? null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        $jointPilgrimage->organizer->notify(new PlatformNotification(
            'Совместное паломничество: '.$this->statuses()[$data['status']],
            'Предложение «'.$jointPilgrimage->title.'» рассмотрено. '.($data['moderation_note'] ?? ''),
            route('together.show', $jointPilgrimage),
            $data['status'] === 'published' ? 'bi-check-circle' : 'bi-info-circle'
        ));

        return back()->with('success', 'Статус предложения обновлён.');
    }

    public function destroy(JointPilgrimage $jointPilgrimage): RedirectResponse
    {
        $jointPilgrimage->delete();

        return back()->with('success', 'Предложение удалено.');
    }

    private function statuses(): array
    {
        return [
            'pending' => 'На модерации',
            'published' => 'Опубликовано',
            'rejected' => 'Отклонено',
            'cancelled' => 'Отменено',
            'completed' => 'Завершено',
        ];
    }
}
