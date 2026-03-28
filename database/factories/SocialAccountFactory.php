<?php

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccount> */
class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialProvider::Google,
            'provider_id' => fake()->unique()->numerify('##########'),
            'provider_email' => fake()->safeEmail(),
            'avatar_url' => fake()->imageUrl(100, 100, 'people'),
        ];
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => SocialProvider::Google,
        ]);
    }

    public function apple(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => SocialProvider::Apple,
        ]);
    }
}
