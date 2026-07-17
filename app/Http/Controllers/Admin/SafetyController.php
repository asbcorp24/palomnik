<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityReport;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SafetyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,in_review,resolved,rejected'],
            'category' => ['nullable', 'string', 'max:64'],
        ]);

        $reports = CommunityReport::query()
            ->with(['reporter', 'reportedUser', 'jointPilgrimage', 'message.user'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_review' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.safety.index', [
            'reports' => $reports,
            'filters' => $filters,
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, CommunityReport $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
            'deactivate_reported_user' => ['nullable', 'boolean'],
        ]);

        $report->update([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? null,
            'resolved_by' => $request->user()->id,
            'resolved_at' => in_array($data['status'], ['resolved', 'rejected'], true) ? now() : null,
        ]);

        if ($request->boolean('deactivate_reported_user') && $report->reportedUser) {
            $report->reportedUser->update(['is_active' => false]);
        }

        $report->reporter->notify(new PlatformNotification(
            'Жалоба рассмотрена',
            'Статус обращения №'.$report->id.': '.$this->statuses()[$data['status']].'. '.($data['resolution_note'] ?? ''),
            route('notifications.index'),
            'bi-shield-check'
        ));

        return back()->with('success', 'Жалоба обновлена.');
    }

    public function verifyOrganizer(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $verified = (bool) $data['verified'];
        $user->update([
            'is_verified_organizer' => $verified,
            'verified_organizer_at' => $verified ? now() : null,
        ]);

        $user->notify(new PlatformNotification(
            $verified ? 'Статус организатора подтверждён' : 'Статус организатора снят',
            $verified
                ? 'В вашем профиле появилась отметка проверенного организатора.'
                : 'Отметка проверенного организатора была снята администратором.',
            route('profile.dashboard'),
            $verified ? 'bi-patch-check' : 'bi-patch-minus'
        ));

        return back()->with('success', 'Статус организатора обновлён.');
    }

    private function categories(): array
    {
        return [
            'spam' => 'Спам',
            'fraud' => 'Мошенничество',
            'abuse' => 'Оскорбления или преследование',
            'unsafe_meeting' => 'Небезопасная встреча',
            'inappropriate_content' => 'Недопустимый контент',
            'other' => 'Другое',
        ];
    }

    private function statuses(): array
    {
        return [
            'open' => 'Открыта',
            'in_review' => 'На проверке',
            'resolved' => 'Решена',
            'rejected' => 'Не подтверждена',
        ];
    }
}
