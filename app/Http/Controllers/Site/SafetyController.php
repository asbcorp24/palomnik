<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CommunityReport;
use App\Models\JointPilgrimageMessage;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\PlatformNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class SafetyController extends Controller
{
    public function report(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'joint_pilgrimage_id' => ['nullable', 'integer', 'exists:joint_pilgrimages,id'],
            'joint_pilgrimage_message_id' => ['nullable', 'integer', 'exists:joint_pilgrimage_messages,id'],
            'category' => ['required', Rule::in(['spam', 'fraud', 'abuse', 'unsafe_meeting', 'inappropriate_content', 'other'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        abort_if((int) ($data['reported_user_id'] ?? 0) === (int) $request->user()->id, 422, 'Нельзя пожаловаться на самого себя.');
        abort_unless(($data['reported_user_id'] ?? null) || ($data['joint_pilgrimage_id'] ?? null) || ($data['joint_pilgrimage_message_id'] ?? null), 422, 'Не указан объект жалобы.');

        if (! empty($data['joint_pilgrimage_message_id'])) {
            $message = JointPilgrimageMessage::query()->findOrFail($data['joint_pilgrimage_message_id']);
            if (! empty($data['joint_pilgrimage_id'])) {
                abort_unless((int) $message->joint_pilgrimage_id === (int) $data['joint_pilgrimage_id'], 422);
            }
            $data['joint_pilgrimage_id'] = $message->joint_pilgrimage_id;
            $data['reported_user_id'] = $data['reported_user_id'] ?? $message->user_id;
        }

        $report = CommunityReport::query()->create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'status' => 'open',
        ]);

        $admins = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->where('is_active', true)->get();
        Notification::send($admins, new PlatformNotification(
            'Новая жалоба сообщества',
            $request->user()->name.' отправил жалобу категории «'.$data['category'].'».',
            route('admin.safety.index'),
            'bi-shield-exclamation'
        ));

        return back()->with('success', 'Жалоба отправлена модераторам. Номер обращения: '.$report->id.'.');
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'Нельзя заблокировать самого себя.');

        UserBlock::query()->firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);

        return redirect()->route('together.index')
            ->with('success', 'Пользователь заблокирован. Его предложения и сообщения скрыты для вас.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return back()->with('success', 'Пользователь разблокирован.');
    }
}
