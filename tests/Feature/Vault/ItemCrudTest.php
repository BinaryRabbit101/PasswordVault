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

test('an item can be created with folder, totp and custom fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('items.store'), [
            'vault_id' => $user->personalVault()->id,
            'name' => 'Chase Bank',
            'url' => 'https://chase.com',
            'username' => 'jane@example.com',
            'password' => 'hunter2',
            'folder' => 'Finance',
            'favorite' => true,
            'totp_secret' => 'otpauth://totp/Chase:jane?secret=JBSWY3DPEHPK3PXP&issuer=Chase',
            'fields' => [
                ['label' => 'PIN', 'type' => 'password', 'value' => '1234', 'is_secret' => true],
            ],
        ])
        ->assertRedirect(route('vault.index'));

    $item = Item::firstWhere('name', 'Chase Bank');

    expect($item->folder->name)->toBe('Finance')
        ->and($item->favorite)->toBeTrue()
        ->and($item->totp_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($item->fields)->toHaveCount(1)
        ->and($item->fields->first()->value)->toBe('1234');
});

test('creating an identical item in the same vault is rejected', function () {
    $user = User::factory()->create();
    $vaultId = $user->personalVault()->id;

    $payload = [
        'vault_id' => $vaultId,
        'name' => 'Example',
        'url' => 'https://example.com',
        'username' => 'jane',
    ];

    $this->actingAs($user)->post(route('items.store'), $payload)->assertRedirect();
    $this->actingAs($user)
        ->post(route('items.store'), $payload)
        ->assertSessionHasErrors('name');

    expect(Item::where('vault_id', $vaultId)->count())->toBe(1);
});

test('an item can be updated and its fields replaced', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => $user->personalVault()->id]);
    $item->fields()->create(['label' => 'Old', 'type' => 'text', 'value' => 'old', 'is_secret' => false, 'sort_order' => 0]);

    $this->actingAs($user)
        ->put(route('items.update', $item), [
            'name' => 'Renamed',
            'password' => 'new-password',
            'fields' => [
                ['label' => 'New', 'type' => 'text', 'value' => 'new', 'is_secret' => false],
            ],
        ])
        ->assertRedirect(route('vault.index'));

    $fresh = $item->fresh();

    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->password)->toBe('new-password')
        ->and($fresh->fields)->toHaveCount(1)
        ->and($fresh->fields->first()->label)->toBe('New');
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
            'totp_secret' => 'not!valid@base32',
        ])
        ->assertSessionHasErrors('totp_secret');
});
