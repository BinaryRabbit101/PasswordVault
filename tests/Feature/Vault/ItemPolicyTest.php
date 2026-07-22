<?php

use App\Models\Item;
use App\Models\User;
use App\Models\Vault;

function vaultWithMember(User $user): Vault
{
    $vault = Vault::factory()->shared()->create();
    $user->vaults()->attach($vault);

    return $vault;
}

test('a member can fetch item secrets', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => vaultWithMember($user)->id]);

    $this->actingAs($user)
        ->getJson(route('items.secrets', $item))
        ->assertOk()
        ->assertJsonPath('password', $item->password)
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a non-member cannot fetch item secrets', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => vaultWithMember($member)->id]);

    $this->actingAs($outsider)
        ->getJson(route('items.secrets', $item))
        ->assertForbidden();
});

test('a non-member cannot update or delete an item', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => vaultWithMember($member)->id]);

    $this->actingAs($outsider)
        ->put(route('items.update', $item), ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('items.destroy', $item))
        ->assertForbidden();

    expect($item->fresh()->name)->not->toBe('Hijacked');
});

test('items cannot be created in a vault the user is not a member of', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $vault = vaultWithMember($member);

    $this->actingAs($outsider)
        ->post(route('items.store'), [
            'vault_id' => $vault->id,
            'name' => 'Sneaky',
        ])
        ->assertSessionHasErrors('vault_id');
});

test('the vault index only lists items from the user vaults', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Mine',
    ]);
    Item::factory()->create([
        'vault_id' => $other->personalVault()->id,
        'name' => 'Not mine',
    ]);

    $this->actingAs($user)
        ->get(route('vault.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vault/Index')
            ->has('items', 1)
            ->where('items.0.name', 'Mine')
            ->where('items.0.id', $mine->id));
});
