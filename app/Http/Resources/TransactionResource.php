<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Controls exactly which fields are exposed in API responses.
 * Never return raw model data — always go through a Resource.
 */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'amount_centavos' => $this->amount_centavos,
            'amount_php'     => $this->amount_php,      // computed accessor
            'currency'       => $this->currency,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'was_offline'    => $this->was_offline,
            'notes'          => $this->notes,
            'initiated_at'   => $this->initiated_at,
            'created_at'     => $this->created_at,
            // Include fraud flags only if loaded (avoids N+1)
            'fraud_flags'    => $this->whenLoaded('fraudFlags'),
        ];
    }
}
