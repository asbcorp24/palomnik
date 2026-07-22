<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
  'verification.verify',
  Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
  [
      'id' => $notifiable->getKey(),
      'hash' => sha1($notifiable->getEmailForVerification()),
  ]
        );

        return (new MailMessage)
  ->subject('Подтверждение email — Московский паломник')
  ->greeting('Здравствуйте, '.$notifiable->name.'!')
  ->line('Подтвердите адрес электронной почты, чтобы пользоваться личным кабинетом, бронированиями и сообществом.')
  ->action('Подтвердить email', $url)
  ->line('Ссылка действует ограниченное время. Если вы не создавали аккаунт, просто проигнорируйте письмо.');
    }
}
