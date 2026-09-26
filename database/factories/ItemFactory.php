<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Vault;
use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Items are created as a login (username, password, website) unless given
 * other fields with withFields().
 *
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
            'favorite' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Item $item): void {
            if ($item->fields()->exists()) {
                return;
            }

            self::putFields($item, Item::loginFields(fake()->userName(), fake()->password(16), fake()->url()));
        });
    }

    public function favorite(): static
    {
        return $this->state(fn () => ['favorite' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function withFields(array $fields): static
    {
        return $this->afterCreating(fn (Item $item) => self::putFields($item, $fields));
    }

    public function login(?string $username = null, ?string $password = null, ?string $url = null, ?string $totp = null, ?string $notes = null): static
    {
        return $this->withFields(Item::loginFields($username, $password, $url, $totp, $notes));
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private static function putFields(Item $item, array $fields): void
    {
        $item->syncFields($fields);

        // Not saveQuietly(): that would skip the dedup-hash hook too.
        ItemObserver::$muted = true;

        try {
            $item->save();
        } finally {
            ItemObserver::$muted = false;
        }
    }
}
