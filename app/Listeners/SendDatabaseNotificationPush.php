<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

class SendDatabaseNotificationPush
{
    public function __construct(private FirebasePushService $push)
    {
    }

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        try {
            $payload = $event->notification->toArray($event->notifiable);
            $this->push->sendToUser(
                $event->notifiable,
                (string) ($payload['title'] ?? 'Московский паломник'),
                (string) ($payload['body'] ?? ''),
                $payload['url'] ?? null,
                [
                    'notification_type' => class_basename($event->notification),
                ]
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
