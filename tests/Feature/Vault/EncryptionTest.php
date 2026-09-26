<?php

use App\Models\Item;
use App\Models\ItemField;
use Illuminate\Support\Facades\DB;

test('field values and the derived username are encrypted at rest', function () {
    $item = Item::factory()
        ->login('jane@example.com', 'super-secret-password', totp: 'JBSWY3DPEHPK3PXP', notes: 'private notes')
        ->create();

    foreach ($item->fields as $field) {
        $raw = DB::table('item_fields')->where('id', $field->id)->value('value');

        expect($raw)->not->toContain($field->value);
    }

    expect(DB::table('items')->where('id', $item->id)->value('username'))->not->toContain('jane@example.com');

    $fresh = $item->fresh();
    expect($fresh->username)->toBe('jane@example.com')
        ->and($fresh->loginPassword())->toBe('super-secret-password')
        ->and($fresh->totpSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($fresh->fields->firstWhere('type', 'note')->value)->toBe('private notes');
});

test('custom field values are encrypted at rest', function () {
    $field = ItemField::factory()->create(['value' => 'a-secret-value']);

    $raw = DB::table('item_fields')->where('id', $field->id)->value('value');

    expect($raw)->not->toContain('a-secret-value')
        ->and($field->fresh()->value)->toBe('a-secret-value');
});

test('dedup hash is stable across saves and ignores case', function () {
    $item = Item::factory()->login('Jane', 'pw', 'https://example.com')->create(['name' => 'Example']);

    $hash = $item->dedup_hash;

    $item->update(['favorite' => true]);

    expect($item->fresh()->dedup_hash)->toBe($hash)
        ->and(Item::dedupHashFor('EXAMPLE', 'https://EXAMPLE.com', 'jane'))->toBe($hash);
});
