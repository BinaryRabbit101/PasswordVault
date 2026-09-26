<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ItemFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One piece of an item's content — a username, a password, a bot token, a
 * website, a note. Only `password` fields are concealed.
 *
 * @property int $id
 * @property int $item_id
 * @property string $label
 * @property string $type
 * @property string|null $autofill
 * @property string|null $value
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label', 'type', 'autofill', 'value', 'sort_order'])]
class ItemField extends Model
{
    /** @use HasFactory<ItemFieldFactory> */
    use HasFactory;

    public const TYPES = ['text', 'password', 'email', 'url', 'totp', 'note'];

    public const AUTOFILL_USERNAME = 'username';

    public const AUTOFILL_PASSWORD = 'password';

    public const AUTOFILL_NONE = 'none';

    public const AUTOFILL = [self::AUTOFILL_USERNAME, self::AUTOFILL_PASSWORD, self::AUTOFILL_NONE];

    /** Previous values kept per field; older ones are pruned on each change. */
    public const HISTORY_LIMIT = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ItemField $field): void {
            if (! $field->isDirty('value')) {
                return;
            }

            $previous = $field->getOriginal('value');

            if ($previous === null || $previous === '') {
                return;
            }

            $field->histories()->create(['value' => $previous]);
            $field->pruneHistory();
        });
    }

    public function pruneHistory(): void
    {
        $keep = $this->histories()->limit(self::HISTORY_LIMIT)->pluck('id');

        $this->histories()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Newest first.
     *
     * @return HasMany<ItemFieldHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ItemFieldHistory::class)->latest('created_at')->latest('id');
    }
}
