<?php

namespace App\Notifications;

use App\Models\EmployeeShift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, EmployeeShift>  $assignments
     */
    public function __construct(public readonly Collection $assignments) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sorted = $this->assignments->sortBy('date');
        $first = $sorted->first();
        $last = $sorted->last();

        $mail = (new MailMessage)
            ->subject('Vaktir þínar hafa verið birtar — Timr')
            ->greeting('Hæ, '.$notifiable->name.'!')
            ->line('Vaktir þínar hafa verið birtar. Hér eru komandi vaktirnar þínar:')
            ->line('');

        foreach ($sorted as $assignment) {
            $date = $assignment->date->translatedFormat('l, j. F Y');
            $start = substr($assignment->shift->start_time, 0, 5);
            $end = substr($assignment->shift->end_time, 0, 5);
            $mail->line("📅 **{$date}** — {$assignment->shift->title} ({$start}–{$end})");
        }

        $mail->line('')
            ->action('Sjá vaktayfirlit', config('app.frontend_url').'/dashboard/my-shifts')
            ->line('Vinsamlegast hafðu samband við yfirmann þinn ef eitthvað er rangt.')
            ->salutation('Kveðja, Timr');

        return $mail;
    }
}
