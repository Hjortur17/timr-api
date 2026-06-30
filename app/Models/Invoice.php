<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'company_id',
        'number',
        'amount',
        'currency',
        'status',
        'period_start',
        'period_end',
        'issued_at',
        'paid_at',
        'verifone_reference',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
