<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Employee extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'user_id',
        'ssn',
        'name',
        'email',
        'phone',
        'invite_token',
        'invite_sent_at',
        'calendar_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'invite_sent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('company_id', auth()->user()->company_id);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class)->withPivot('date', 'published')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function clockEntries(): HasMany
    {
        return $this->hasMany(ClockEntry::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Check whether the employee has opted in to a notification type.
     * Defaults to true (enabled) if no preference record exists.
     */
    public function prefersNotification(NotificationType $type): bool
    {
        $preference = $this->notificationPreferences
            ->firstWhere('type', $type);

        return $preference === null || $preference->enabled;
    }

    /**
     * Route notifications to the employee's own email address.
     *
     * @return array<string, string>
     */
    public function routeNotificationForMail(): array
    {
        return [$this->email => $this->name];
    }
}
