<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/redefinir-senha/'.$this->token.'?email='.urlencode($notifiable->email));

        return (new MailMessage)
            ->subject('Redefinição de senha - Comere')
            ->line('Você solicitou a redefinição da senha da sua conta Comere.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em 60 minutos.')
            ->line('Se você não solicitou isso, pode ignorar este e-mail.');
    }
}
