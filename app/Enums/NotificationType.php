<?php

namespace App\Enums;

enum NotificationType: string
{
    case ShiftPublished = 'shift_published';
    case ShiftChanged = 'shift_changed';
    case ShiftReminder = 'shift_reminder';

    public function label(): string
    {
        return match ($this) {
            self::ShiftPublished => 'Vaktir birtar',
            self::ShiftChanged => 'Vakt breytt eða eytt',
            self::ShiftReminder => 'Áminning fyrir vakt',
        };
    }
}
