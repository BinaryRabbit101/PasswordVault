<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vault;
use Illuminate\Console\Command;

class ShareVault extends Command
{
    protected $signature = 'vault:share
        {user : Email address of the user to attach}
        {--vault=Household : Name of the shared vault}';

    protected $description = 'Attach a user to a shared vault (creates the vault if missing)';

    public function handle(): int
    {
        $email = (string) $this->argument('user');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $vault = Vault::firstOrCreate(
            ['type' => Vault::TYPE_SHARED, 'name' => (string) $this->option('vault')],
            ['created_by' => $user->id],
        );

        $user->vaults()->syncWithoutDetaching($vault);

        $this->info("{$user->name} is a member of \"{$vault->name}\".");

        return self::SUCCESS;
    }
}
