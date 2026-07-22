<?php

namespace App\Notifications;

use App\Models\Item;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells other members of a shared vault that an item changed.
 * Never includes secret values — only the item name and action.
 */
class SharedItemChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Item $item,
        public User $actor,
        public string $action, // 'added' | 'updated' | 'removed'
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->item->vault->name)
            ->body("{$this->actor->name} {$this->action} \"{$this->item->name}\".")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag("item-{$this->item->id}")
            ->data(['url' => '/vault']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'vault_id' => $this->item->vault_id,
            'actor' => $this->actor->name,
            'action' => $this->action,
        ];
    }
}
