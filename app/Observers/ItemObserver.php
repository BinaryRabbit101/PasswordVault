<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\User;
use App\Models\Vault;
use App\Notifications\SharedItemChangedNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Notifies the other members of a shared vault when an item changes.
 */
class ItemObserver
{
    /**
     * Muted during bulk imports so a LastPass import doesn't flood the other
     * member with hundreds of pushes.
     */
    public static bool $muted = false;

    public function created(Item $item): void
    {
        $this->notifyOthers($item, 'added');
    }

    public function updated(Item $item): void
    {
        $this->notifyOthers($item, 'updated');
    }

    public function deleted(Item $item): void
    {
        $this->notifyOthers($item, 'removed');
    }

    protected function notifyOthers(Item $item, string $action): void
    {
        if (self::$muted) {
            return;
        }

        $actor = Auth::user();

        if (! $actor instanceof User) {
            return; // console imports, seeding, etc.
        }

        $vault = $item->vault;

        if (! $vault || $vault->type !== Vault::TYPE_SHARED) {
            return;
        }

        $vault->users
            ->reject(fn (User $user) => $user->is($actor))
            ->each(fn (User $user) => $user->notify(
                new SharedItemChangedNotification($item, $actor, $action),
            ));
    }
}
