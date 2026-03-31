<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClockEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'employee_id',
        'clocked_in_at',
        'clocked_out_at',
        'clock_in_lat',
        'clock_in_lng',
    ];

    protected function casts(): array
    {
        return [
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
            'clock_in_lat' => 'float',
            'clock_in_lng' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $companyId = auth()->user()->company_id;
                $builder->where(function (Builder $query) use ($companyId) {
                    $query->whereHas('shift', function (Builder $q) use ($companyId) {
                        $q->withoutGlobalScope('company')
                            ->withTrashed()
                            ->where('company_id', $companyId);
                    })->orWhereHas('employee', function (Builder $q) use ($companyId) {
                        $q->withoutGlobalScope('company')
                            ->where('company_id', $companyId);
                    });
                });
            }
        });
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
