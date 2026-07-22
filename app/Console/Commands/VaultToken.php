<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VaultToken extends Command
{
    protected $signature = 'vault:token
        {user : Email address of the user}
        {--rotate : Replace any existing token (revokes the old one)}
        {--fill : Target the in-page autofill filler token instead of the device token}';

    protected $description = 'Mint or rotate a token used by the iOS Shortcut / widget API';

    public function handle(): int
    {
        $email = (string) $this->argument('user');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $column = $this->option('fill') ? 'fill_token' : 'device_token';
        $label = $this->option('fill') ? 'Fill token' : 'Device token';

        if ($user->{$column} !== null && ! $this->option('rotate')) {
            $this->info('A token already exists. Use --rotate to replace it.');
            $this->line($user->{$column});

            return self::SUCCESS;
        }

        $user->forceFill([$column => Str::random(48)])->save();

        $this->info("{$label} for {$user->name}:");
        $this->line($user->{$column});

        return self::SUCCESS;
    }
}
