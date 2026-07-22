<?php

namespace App\Models;

use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property int $vault_id
 * @property int|null $folder_id
 * @property string $name
 * @property string|null $url
 * @property string|null $username
 * @property string|null $password
 * @property string|null $notes
 * @property string|null $totp_secret
 * @property bool $favorite
 * @property string $dedup_hash
 * @property CarbonImmutable|null $password_updated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'vault_id', 'folder_id', 'name', 'url', 'username', 'password',
    'notes', 'totp_secret', 'favorite',
])]
#[ObservedBy(ItemObserver::class)]
class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'password' => 'encrypted',
            'notes' => 'encrypted',
            'totp_secret' => 'encrypted',
            'favorite' => 'boolean',
            'password_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            $item->dedup_hash = self::dedupHashFor($item->name, $item->url, $item->username);

            if ($item->isDirty('password')) {
                $item->password_updated_at = now();
            }
        });
    }

    public static function dedupHashFor(string $name, ?string $url, ?string $username): string
    {
        return hash('sha256', mb_strtolower($name).'|'.mb_strtolower($url ?? '').'|'.mb_strtolower($username ?? ''));
    }

    /**
     * @return BelongsTo<Vault, $this>
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<ItemField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(ItemField::class)->orderBy('sort_order');
    }
}
