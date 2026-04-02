<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Endurstilling lykilorðs — Timr')
            ->greeting('Hæ, '.$notifiable->name.'!')
            ->line('Þú fékkst þennan tölvupóst vegna þess að við fengum beiðni um endurstillingu lykilorðs fyrir aðganginn þinn.')
            ->action('Endurstilla lykilorð', $url)
            ->line('Þessi tengill rennur út eftir 60 mínútur.')
            ->line('Ef þú baðst ekki um endurstillingu lykilorðs þarftu ekki að gera neitt.')
            ->salutation('Kveðja, Timr');
    }
}
