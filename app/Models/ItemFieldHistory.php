<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A value a field held before it was changed (at most ItemField::HISTORY_LIMIT
 * are kept per field).
 *
 * @property int $id
 * @property int $item_field_id
 * @property string $value
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['item_field_id', 'value'])]
class ItemFieldHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ItemField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ItemField::class, 'item_field_id');
    }
}
