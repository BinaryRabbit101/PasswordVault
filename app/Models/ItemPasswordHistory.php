<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A previous password an item held before it was changed.
 *
 * @property int $id
 * @property int $item_id
 * @property string $password
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['item_id', 'password'])]
class ItemPasswordHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'created_at' => 'datetime',
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
