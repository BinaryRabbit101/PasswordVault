<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VaultToken extends Command
{
    protected $signature = 'vault:token
        {user : Email address of the user}
        {--rotate : Replace any existing token (revokes the old one)}';

    protected $description = 'Mint or rotate the device token used by the iOS Shortcut / widget API';

    public function handle(): int
    {
        $email = (string) $this->argument('user');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($user->device_token !== null && ! $this->option('rotate')) {
            $this->info('A token already exists. Use --rotate to replace it.');
            $this->line($user->device_token);

            return self::SUCCESS;
        }

        $user->forceFill(['device_token' => Str::random(48)])->save();

        $this->info("Device token for {$user->name}:");
        $this->line($user->device_token);

        return self::SUCCESS;
    }
}
