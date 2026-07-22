<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'name' => fake()->company(),
            'url' => fake()->url(),
            'username' => fake()->userName(),
            'password' => fake()->password(16),
            'notes' => null,
            'totp_secret' => null,
            'favorite' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn () => ['favorite' => true]);
    }

    public function withTotp(): static
    {
        return $this->state(fn () => ['totp_secret' => 'JBSWY3DPEHPK3PXP']);
    }
}
