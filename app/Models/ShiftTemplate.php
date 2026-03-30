<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShiftTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'shift_id',
        'name',
        'description',
        'pattern',
        'blocks',
        'cycle_length_days',
    ];

    protected function casts(): array
    {
        return [
            'cycle_length_days' => 'integer',
            'blocks' => 'array',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'shift_template_employee')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }
}
