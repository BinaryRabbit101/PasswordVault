<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'type', 'created_by'])]
class Vault extends Model
{
    /** @use HasFactory<\Database\Factories\VaultFactory> */
    use HasFactory;

    public const TYPE_PERSONAL = 'personal';

    public const TYPE_SHARED = 'shared';

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @param  Builder<Vault>  $query
     * @return Builder<Vault>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('users', fn (Builder $q) => $q->whereKey($user->id));
    }
}
