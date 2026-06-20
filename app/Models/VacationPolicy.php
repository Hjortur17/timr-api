<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'default_days_per_year',
        'vacation_year_start_month',
        'vacation_year_start_day',
        'working_days',
        'opening_hours',
        'allow_carry_over',
        'max_carry_over_days',
    ];

    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'integer',
            'vacation_year_start_month' => 'integer',
            'vacation_year_start_day' => 'integer',
            'working_days' => 'array',
            'opening_hours' => 'array',
            'allow_carry_over' => 'boolean',
            'max_carry_over_days' => 'integer',
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
}
