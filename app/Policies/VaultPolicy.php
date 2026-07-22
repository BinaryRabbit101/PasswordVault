<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;

class VaultPolicy
{
    public function view(User $user, Vault $vault): bool
    {
        return $this->isMember($user, $vault);
    }

    public function update(User $user, Vault $vault): bool
    {
        return $this->isMember($user, $vault);
    }

    protected function isMember(User $user, Vault $vault): bool
    {
        return $vault->users()->whereKey($user->id)->exists();
    }
}
