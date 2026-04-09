<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel_push',
        'channel_email',
        'channel_in_app',
        'timing_value',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'channel_push' => 'boolean',
            'channel_email' => 'boolean',
            'channel_in_app' => 'boolean',
            'timing_value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
