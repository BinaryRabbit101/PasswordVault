<?php

namespace App\Models;

use App\Observers\ItemObserver;
use App\Support\FieldRoles;
use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An item's content lives entirely in its fields. `url`, `urls` and
 * `username` are derived from them (see applyDerived) for the list, search,
 * favicon, autofill matching and dedup — never edited directly.
 *
 * @property int $id
 * @property int $vault_id
 * @property int|null $folder_id
 * @property string $name
 * @property string|null $url First website field, plaintext.
 * @property string|null $urls Every website field, newline-separated, plaintext.
 * @property string|null $username The field autofill uses as the username.
 * @property bool $favorite
 * @property string $dedup_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['vault_id', 'folder_id', 'name', 'favorite'])]
#[ObservedBy(ItemObserver::class)]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'username' => 'encrypted',
            'favorite' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            $item->dedup_hash = self::dedupHashFor($item->name, $item->url, $item->username);
        });
    }

    public static function dedupHashFor(string $name, ?string $url, ?string $username): string
    {
        return hash('sha256', mb_strtolower($name).'|'.mb_strtolower($url ?? '').'|'.mb_strtolower($username ?? ''));
    }

    /**
     * The field rows for a classic login, skipping empty parts — used by the
     * LastPass import and the Login template's shape.
     *
     * @return list<array{label: string, type: string, value: string}>
     */
    public static function loginFields(
        ?string $username = null,
        ?string $password = null,
        ?string $url = null,
        ?string $totp = null,
        ?string $notes = null,
    ): array {
        $rows = [
            ['label' => 'Username', 'type' => 'text', 'value' => $username],
            ['label' => 'Password', 'type' => 'password', 'value' => $password],
            ['label' => 'Website', 'type' => 'url', 'value' => $url],
            ['label' => 'One-time code', 'type' => 'totp', 'value' => $totp],
            ['label' => 'Notes', 'type' => 'note', 'value' => $notes],
        ];

        return array_values(array_filter($rows, fn (array $row) => $row['value'] !== null && $row['value'] !== ''));
    }

    /**
     * Set the derived columns from a set of fields (models or request rows).
     *
     * @param  iterable<ItemField|array<string, mixed>>  $fields
     */
    public function applyDerived(iterable $fields): void
    {
        $urls = FieldRoles::urls($fields);

        $this->url = $urls[0] ?? null;
        $this->urls = $urls === [] ? null : implode("\n", $urls);
        $this->username = FieldRoles::username($fields);
    }

    /**
     * Make the item's fields match `$rows` in order: a row carrying the id of
     * one of this item's fields updates it in place (so its history survives),
     * the rest are created, and fields not sent are deleted. Also refreshes
     * the derived columns; the caller saves the item.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return bool Whether any field changed.
     */
    public function syncFields(array $rows): bool
    {
        $existing = $this->fields()->get()->keyBy('id');
        $kept = [];
        $changed = false;

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'label' => $row['label'],
                'type' => $row['type'],
                'autofill' => $row['autofill'] ?? null,
                'value' => isset($row['value']) && $row['value'] !== '' ? $row['value'] : null,
                'sort_order' => $index,
            ];

            $field = isset($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($field instanceof ItemField) {
                $field->fill($attributes);
                $changed = $changed || $field->isDirty();
                $field->save();
                $kept[] = $field->id;

                continue;
            }

            $kept[] = $this->fields()->create($attributes)->id;
            $changed = true;
        }

        $removed = $existing->keys()->diff($kept);

        if ($removed->isNotEmpty()) {
            $this->fields()->whereIn('id', $removed->all())->delete();
            $changed = true;
        }

        $this->unsetRelation('fields');
        $this->applyDerived($this->fields);

        return $changed;
    }

    public function loginPassword(): ?string
    {
        return FieldRoles::password($this->fields);
    }

    public function totpSecret(): ?string
    {
        return FieldRoles::totp($this->fields);
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
