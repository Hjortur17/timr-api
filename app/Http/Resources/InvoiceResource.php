<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'issued_at' => $this->issued_at,
            'paid_at' => $this->paid_at,
            'pdf_url' => $this->pdf_url,
        ];
    }
}
