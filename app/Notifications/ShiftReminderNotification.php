<?php

namespace App\Notifications;

use App\Models\EmployeeShift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EmployeeShift $assignment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->assignment->shift;
        $date = $this->assignment->date->translatedFormat('l, j. F Y');
        $start = substr($shift->start_time, 0, 5);
        $end = substr($shift->end_time, 0, 5);

        return (new MailMessage)
            ->subject('Áminning: Vakt á morgun — Timr')
            ->greeting('Hæ, '.$notifiable->name.'!')
            ->line('Þetta er áminning um vakt þína á morgun.')
            ->line("📅 **{$date}**")
            ->line("🕐 {$shift->title}: {$start}–{$end}")
            ->action('Sjá vaktayfirlit', config('app.frontend_url').'/dashboard/my-shifts')
            ->salutation('Kveðja, Timr');
    }
}
