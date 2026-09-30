<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'shop_name' => $this->shop_name,
            'business_type' => $this->business_type,
            'locale' => $this->locale,
            'has_pin' => $this->hasPin(),
            'is_profile_complete' => $this->isProfileComplete(),
            'bonus_balance' => (float) $this->bonus_balance,
            'has_referrer' => $this->referred_by_id !== null,
            'plan' => $this->currentPlan(),
            'plan_expires_at' => $this->activeSubscription()?->expires_at?->toIso8601String(),
            'pro_expires_at' => $this->isPro() ? $this->activeSubscription()?->expires_at?->toIso8601String() : null,
            'features' => [
                'sales' => $this->hasSalesAndInventory(),
                'inventory' => $this->hasSalesAndInventory(),
                'pro' => $this->isPro(),
            ],
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
