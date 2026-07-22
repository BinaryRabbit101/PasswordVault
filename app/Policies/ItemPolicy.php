<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function view(User $user, Item $item): bool
    {
        return $this->isMember($user, $item);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->isMember($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->isMember($user, $item);
    }

    public function restore(User $user, Item $item): bool
    {
        return $this->isMember($user, $item);
    }

    protected function isMember(User $user, Item $item): bool
    {
        return $user->vaults()->whereKey($item->vault_id)->exists();
    }
}
