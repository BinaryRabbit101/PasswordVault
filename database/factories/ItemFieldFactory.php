<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemField>
 */
class ItemFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'label' => fake()->word(),
            'type' => 'text',
            'value' => fake()->sentence(),
            'is_secret' => true,
            'sort_order' => 0,
        ];
    }
}
