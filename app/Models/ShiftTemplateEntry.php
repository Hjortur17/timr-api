<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftTemplateEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_template_id',
        'shift_id',
        'employee_id',
        'day_offset',
    ];

    protected function casts(): array
    {
        return [
            'day_offset' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('template', function (Builder $query) {
                    $query->withoutGlobalScope('company')
                        ->where('company_id', auth()->user()->company_id);
                });
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
