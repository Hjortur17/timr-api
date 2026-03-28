<?php

namespace App\Notifications;

use App\Models\EmployeeShift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EmployeeShift $assignment,
        public readonly string $changeType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ["mail"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->assignment->shift;
        $date = $this->assignment->date->translatedFormat("l, j. F Y");
        $start = substr($shift->start_time, 0, 5);
        $end = substr($shift->end_time, 0, 5);

        if ($this->changeType === "deleted") {
            return new MailMessage()
                ->subject("Vakt þinni hefur verið eytt — Timr")
                ->greeting("Hæ, " . $notifiable->name . "!")
                ->line("Vakt þinni þann **{$date}** hefur verið eytt.")
                ->line("Vakt: {$shift->title} ({$start}–{$end})")
                ->action(
                    "Sjá vaktayfirlit",
                    config("app.frontend_url") . "/dashboard/shifts",
                )
                ->line(
                    "Hafðu samband við yfirmann þinn ef þig vantar frekari upplýsingar.",
                )
                ->salutation("Kveðja, Timr");
        }

        return new MailMessage()
            ->subject("Vakt þín hefur verið breytt — Timr")
            ->greeting("Hæ, " . $notifiable->name . "!")
            ->line("Vakt þín þann **{$date}** hefur verið uppfærð.")
            ->line("Vakt: {$shift->title} ({$start}–{$end})")
            ->action(
                "Sjá vaktayfirlit",
                config("app.frontend_url") . "/dashboard/shifts",
            )
            ->line("Hafðu samband við yfirmann þinn ef eitthvað er rangt.")
            ->salutation("Kveðja, Timr");
    }
}
