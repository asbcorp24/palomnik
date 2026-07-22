<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
  'token' => $this->token,
  'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
  ->subject('Восстановление пароля — Московский паломник')
  ->greeting('Здравствуйте, '.$notifiable->name.'!')
  ->line('Получен запрос на восстановление пароля вашего аккаунта.')
  ->action('Задать новый пароль', $url)
  ->line('Ссылка действует ограниченное время. Если запрос отправили не вы, пароль менять не нужно.');
    }
}
