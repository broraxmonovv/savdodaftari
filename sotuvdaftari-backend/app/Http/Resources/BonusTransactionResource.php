<?php

namespace App\Http\Resources;

use App\Models\BonusTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BonusTransaction */
class BonusTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            // Maxfiylik: taklif qilingan foydalanuvchi telefonining oxirgi 4 raqami
            'plan' => $this->payment?->plan,
            'from' => $this->from_user_id === null ? null : ($this->fromUser?->name ?: ('***'.substr((string) $this->fromUser?->phone, -4))),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
