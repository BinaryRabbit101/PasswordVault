<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vault>
 */
class VaultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word().' '.fake()->word().' Vault',
            'type' => Vault::TYPE_PERSONAL,
            'created_by' => User::factory(),
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['type' => Vault::TYPE_SHARED]);
    }
}
