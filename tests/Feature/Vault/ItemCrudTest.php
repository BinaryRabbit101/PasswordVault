<?php

use App\Models\Item;
use App\Models\User;

test('registering a user auto-creates their personal vault', function () {
    $user = User::factory()->create(['name' => 'Jane']);

    $vault = $user->personalVault();

    expect($vault)->not->toBeNull()
        ->and($vault->name)->toBe("Jane's Vault")
        ->and($vault->type)->toBe('personal');
});

test('an item can be created with folder and fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('items.store'), [
            'vault_id' => $user->personalVault()->id,
            'name' => 'Chase Bank',
            'folder' => 'Finance',
            'favorite' => true,
            'fields' => [
                ['label' => 'Email', 'type' => 'email', 'value' => 'jane@example.com'],
                ['label' => 'Password', 'type' => 'password', 'value' => 'hunter2'],
                ['label' => 'Website', 'type' => 'url', 'value' => 'https://chase.com'],
                ['label' => '2FA', 'type' => 'totp', 'value' => 'otpauth://totp/Chase:jane?secret=JBSWY3DPEHPK3PXP&issuer=Chase'],
                ['label' => 'PIN', 'type' => 'password', 'value' => '1234'],
            ],
        ])
        ->assertRedirect(route('vault.index'));

    $item = Item::firstWhere('name', 'Chase Bank');

    expect($item->folder->name)->toBe('Finance')
        ->and($item->favorite)->toBeTrue()
        ->and($item->url)->toBe('https://chase.com')
        ->and($item->username)->toBe('jane@example.com')
        ->and($item->loginPassword())->toBe('hunter2')
        ->and($item->totpSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($item->fields->pluck('label')->all())->toBe(['Email', 'Password', 'Website', '2FA', 'PIN']);
});

test('an item needs no username or password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('items.store'), [
            'vault_id' => $user->personalVault()->id,
            'name' => 'Discord bot',
            'fields' => [
                ['label' => 'Application ID', 'type' => 'text', 'value' => '1234567890', 'autofill' => 'none'],
                ['label' => 'Bot token', 'type' => 'password', 'value' => 'MTIz.abc.def', 'autofill' => 'none'],
                ['label' => 'Portal', 'type' => 'url', 'value' => 'https://discord.com/developers/applications'],
            ],
        ])
        ->assertRedirect(route('vault.index'));

    $item = Item::firstWhere('name', 'Discord bot');

    expect($item->username)->toBeNull()
        ->and($item->loginPassword())->toBeNull()
        ->and($item->fields)->toHaveCount(3);
});

test('creating an identical item in the same vault is rejected', function () {
    $user = User::factory()->create();
    $vaultId = $user->personalVault()->id;

    $payload = [
        'vault_id' => $vaultId,
        'name' => 'Example',
        'fields' => [
            ['label' => 'Username', 'type' => 'text', 'value' => 'jane'],
            ['label' => 'Website', 'type' => 'url', 'value' => 'https://example.com'],
        ],
    ];

    $this->actingAs($user)->post(route('items.store'), $payload)->assertRedirect();
    $this->actingAs($user)
        ->post(route('items.store'), $payload)
        ->assertSessionHasErrors('name');

    expect(Item::where('vault_id', $vaultId)->count())->toBe(1);
});

test('updating keeps a field whose id is sent and replaces the rest', function () {
    $user = User::factory()->create();
    $item = Item::factory()->login('jane', 'old-password')->create(['vault_id' => $user->personalVault()->id]);
    [$username, $password] = $item->fields->all();

    $this->actingAs($user)
        ->put(route('items.update', $item), [
            'name' => 'Renamed',
            'fields' => [
                ['id' => $password->id, 'label' => 'Password', 'type' => 'password', 'value' => 'new-password'],
                ['label' => 'Recovery code', 'type' => 'password', 'value' => 'abc-123', 'autofill' => 'none'],
            ],
        ])
        ->assertRedirect(route('vault.index'));

    $fresh = $item->fresh();

    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->username)->toBeNull()
        ->and($fresh->loginPassword())->toBe('new-password')
        ->and($fresh->fields->pluck('label')->all())->toBe(['Password', 'Recovery code'])
        ->and($fresh->fields->first()->id)->toBe($password->id)
        ->and($username->fresh())->toBeNull();
});

test('a field id from another item is treated as a new field', function () {
    $user = User::factory()->create();
    $mine = Item::factory()->create(['vault_id' => $user->personalVault()->id]);
    $theirs = Item::factory()->login('them', 'their-password')->create();
    $foreign = $theirs->fields->first();

    $this->actingAs($user)
        ->put(route('items.update', $mine), [
            'name' => $mine->name,
            'fields' => [
                ['id' => $foreign->id, 'label' => 'Stolen', 'type' => 'text', 'value' => 'x'],
            ],
        ])
        ->assertRedirect();

    expect($foreign->fresh()->label)->toBe('Username')
        ->and($mine->fresh()->fields->first()->id)->not->toBe($foreign->id);
});

test('an item can be soft deleted', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => $user->personalVault()->id]);

    $this->actingAs($user)
        ->delete(route('items.destroy', $item))
        ->assertRedirect(route('vault.index'));

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});

test('an invalid totp secret is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('items.store'), [
            'vault_id' => $user->personalVault()->id,
            'name' => 'Bad TOTP',
            'fields' => [
                ['label' => 'One-time code', 'type' => 'totp', 'value' => 'not!valid@base32'],
            ],
        ])
        ->assertSessionHasErrors('fields.0.value');
});
