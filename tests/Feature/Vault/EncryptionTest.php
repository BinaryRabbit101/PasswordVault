<?php

use App\Models\Item;
use App\Models\ItemField;
use Illuminate\Support\Facades\DB;

test('item secrets are encrypted at rest', function () {
    $item = Item::factory()->create([
        'username' => 'jane@example.com',
        'password' => 'super-secret-password',
        'notes' => 'private notes',
        'totp_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $raw = DB::table('items')->where('id', $item->id)->first();

    foreach (['username', 'password', 'notes', 'totp_secret'] as $column) {
        expect($raw->{$column})->not->toContain($item->{$column});
    }

    $fresh = $item->fresh();
    expect($fresh->username)->toBe('jane@example.com')
        ->and($fresh->password)->toBe('super-secret-password')
        ->and($fresh->notes)->toBe('private notes')
        ->and($fresh->totp_secret)->toBe('JBSWY3DPEHPK3PXP');
});

test('custom field values are encrypted at rest', function () {
    $field = ItemField::factory()->create(['value' => 'a-secret-value']);

    $raw = DB::table('item_fields')->where('id', $field->id)->value('value');

    expect($raw)->not->toContain('a-secret-value')
        ->and($field->fresh()->value)->toBe('a-secret-value');
});

test('dedup hash is stable across saves and ignores case', function () {
    $item = Item::factory()->create([
        'name' => 'Example',
        'url' => 'https://example.com',
        'username' => 'Jane',
    ]);

    $hash = $item->dedup_hash;

    $item->update(['notes' => 'changed something else']);

    expect($item->fresh()->dedup_hash)->toBe($hash)
        ->and(Item::dedupHashFor('EXAMPLE', 'https://EXAMPLE.com', 'jane'))->toBe($hash);
});

test('changing the password bumps password_updated_at', function () {
    $item = Item::factory()->create(['password' => 'first']);
    $original = $item->password_updated_at;

    $this->travel(1)->hours();
    $item->update(['password' => 'second']);

    expect($item->fresh()->password_updated_at->gt($original))->toBeTrue();
});
