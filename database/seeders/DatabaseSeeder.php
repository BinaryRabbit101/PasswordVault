<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // The shared household vault every member is attached to. New users
        // registering later are attached via `php artisan vault:share`.
        $household = Vault::firstOrCreate(
            ['type' => Vault::TYPE_SHARED, 'name' => 'Household'],
            ['created_by' => $user->id],
        );

        User::all()->each(
            fn (User $u) => $u->vaults()->syncWithoutDetaching($household),
        );
    }
}
