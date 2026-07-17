<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('site.notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->notifiable_id === (int) $request->user()->id, 403);
        $notification->markAsRead();

        $url = data_get($notification->data, 'url');

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Все уведомления отмечены как прочитанные.');
    }
}
