<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'event_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
