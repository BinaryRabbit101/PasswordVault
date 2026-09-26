<?php

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

test('the fields migration moves fixed columns and password history into fields', function () {
    $user = User::factory()->create();
    $vaultId = $user->personalVault()->id;

    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

    $enc = fn (string $value) => Crypt::encryptString($value);
    $now = now();

    $itemId = DB::table('items')->insertGetId([
        'vault_id' => $vaultId,
        'name' => 'Legacy',
        'url' => 'https://legacy.test',
        'username' => $enc('jane'),
        'password' => $enc('current'),
        'notes' => $enc('a note'),
        'totp_secret' => $enc('JBSWY3DPEHPK3PXP'),
        'favorite' => false,
        'dedup_hash' => Item::dedupHashFor('Legacy', 'https://legacy.test', 'jane'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('item_password_histories')->insert([
        ['item_id' => $itemId, 'password' => $enc('oldest'), 'created_at' => $now->copy()->subDays(2)],
        ['item_id' => $itemId, 'password' => $enc('older'), 'created_at' => $now->copy()->subDay()],
    ]);
    DB::table('item_fields')->insert([
        ['item_id' => $itemId, 'label' => 'PIN', 'type' => 'text', 'value' => $enc('1234'), 'is_secret' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['item_id' => $itemId, 'label' => 'Hint', 'type' => 'text', 'value' => $enc('blue'), 'is_secret' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->artisan('migrate')->assertSuccessful();

    $item = Item::findOrFail($itemId);

    expect($item->fields->map(fn ($f) => [$f->label, $f->type, $f->value])->all())->toBe([
        ['Username', 'text', 'jane'],
        ['Password', 'password', 'current'],
        ['Website', 'url', 'https://legacy.test'],
        ['One-time code', 'totp', 'JBSWY3DPEHPK3PXP'],
        ['PIN', 'password', '1234'],
        ['Hint', 'text', 'blue'],
        ['Notes', 'note', 'a note'],
    ])
        ->and($item->urls)->toBe('https://legacy.test')
        ->and($item->username)->toBe('jane')
        ->and($item->loginPassword())->toBe('current')
        ->and($item->fields[1]->histories->pluck('value')->all())->toBe(['older', 'oldest']);
});
