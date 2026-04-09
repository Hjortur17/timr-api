<?php

namespace App\Models;

use App\Enums\CompanyRole;
use App\Enums\NotificationType;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'is_active',
        'onboarding_step',
        'locale',
        'notifications_paused',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notifications_paused' => 'boolean',
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

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Check whether the user has opted in to a notification type on a given channel.
     * Defaults to true (enabled) if no preference record exists.
     */
    public function prefersNotification(NotificationType $type, ?string $channel = null): bool
    {
        $preference = $this->notificationPreferences
            ->firstWhere('notification_type', $type);

        if ($preference === null) {
            return true;
        }

        if ($channel === null) {
            return $preference->channel_push || $preference->channel_email || $preference->channel_in_app;
        }

        return match ($channel) {
            'push' => $preference->channel_push,
            'email' => $preference->channel_email,
            'in_app' => $preference->channel_in_app,
            default => false,
        };
    }

    public function companyRole(?int $companyId = null): ?CompanyRole
    {
        $companyId ??= $this->company_id;

        if (! $companyId) {
            return null;
        }

        $pivot = $this->companies()->where('company_id', $companyId)->first()?->pivot;

        return $pivot ? CompanyRole::tryFrom($pivot->role) : null;
    }

    /**
     * @param  CompanyRole|CompanyRole[]  $roles
     */
    public function hasCompanyRole(CompanyRole|array $roles, ?int $companyId = null): bool
    {
        $role = $this->companyRole($companyId);

        if (! $role) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($role, $roles);
    }

    public function isManager(?int $companyId = null): bool
    {
        return $this->hasCompanyRole(CompanyRole::managerRoles(), $companyId);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
