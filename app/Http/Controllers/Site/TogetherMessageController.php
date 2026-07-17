<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\JointPilgrimage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TogetherMessageController extends Controller
{
    public function index(Request $request, JointPilgrimage $jointPilgrimage): View
    {
        $user = $request->user();
        $membership = $jointPilgrimage->members()
            ->where('user_id', $user->id)
            ->first();

        $canDiscuss = $jointPilgrimage->organizer_id === $user->id
            || $user->isAdmin()
            || optional($membership)->status === 'approved';

        abort_unless($canDiscuss, 403);

        $blockedIds = $user->blockedUsers()->pluck('blocked_id')
            ->merge($user->blockedByUsers()->pluck('blocker_id'))
            ->unique();

        $messages = $jointPilgrimage->messages()
            ->with('user')
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->reject(fn ($message) => $blockedIds->contains($message->user_id))
            ->values();

        return view('site.together.partials.messages', [
            'messages' => $messages,
            'currentUserId' => $user->id,
        ]);
    }
}
