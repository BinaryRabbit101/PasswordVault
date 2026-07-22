<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property int $item_id
 * @property string $label
 * @property string $type
 * @property string|null $value
 * @property bool $is_secret
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label', 'type', 'value', 'is_secret', 'sort_order'])]
class ItemField extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFieldFactory> */
    use HasFactory;

    public const TYPES = ['text', 'password', 'url', 'note', 'totp', 'email'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
