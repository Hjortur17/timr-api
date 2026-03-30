<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShift extends Model
{
    use HasFactory;

    protected $table = 'employee_shift';

    protected $fillable = [
        'shift_id',
        'employee_id',
        'date',
        'published',
        'published_date',
        'published_employee_id',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'published' => 'boolean',
            'published_date' => 'date',
            'published_employee_id' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function hasUnpublishedChanges(): bool
    {
        if (! $this->published || $this->published_date === null) {
            return false;
        }

        return $this->date->toDateString() !== $this->published_date->toDateString()
            || $this->employee_id !== $this->published_employee_id;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('shift', function (Builder $query) {
                    $query->withoutGlobalScope('company')
                        ->withTrashed()
                        ->where('company_id', auth()->user()->company_id);
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
