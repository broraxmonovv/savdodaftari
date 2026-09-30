<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Admin uchun: to'liq karta raqami va foydalanuvchi ma'lumoti bilan */
class AdminWithdrawalResource extends WithdrawalResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'card_number' => $this->card_number,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
            ],
        ];
    }
}
