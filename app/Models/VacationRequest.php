<?php

namespace App\Models;

use App\Enums\VacationRequestStatus;
use App\Enums\VacationRequestType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'start_date',
        'end_date',
        'working_days_count',
        'status',
        'type',
        'employee_note',
        'reviewer_note',
        'reviewed_by',
        'reviewed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'working_days_count' => 'integer',
            'status' => VacationRequestStatus::class,
            'type' => VacationRequestType::class,
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
