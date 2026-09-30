<?php

namespace App\Http\Resources;

use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Withdrawal */
class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'card' => $this->maskedCard(),
            'card_holder' => $this->card_holder,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        return $data;
    }
}
