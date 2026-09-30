<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'phone' => '+9989'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'shop_name' => fake()->company(),
            'business_type' => 'boshqa',
            'locale' => 'uz',
            'phone_verified_at' => now(),
            'pin' => '1234',
        ];
    }

    /** Faol Standart tarifli foydalanuvchi (savdo va ombor ochiq) */
    public function standard(): static
    {
        return $this->withPlan(Subscription::PLAN_STANDARD);
    }

    /** Faol Pro tarifli foydalanuvchi */
    public function pro(): static
    {
        return $this->withPlan(Subscription::PLAN_PRO);
    }

    private function withPlan(string $plan): static
    {
        return $this->afterCreating(fn (User $user) => Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]));
    }
}
