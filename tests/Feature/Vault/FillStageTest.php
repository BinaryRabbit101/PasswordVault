<?php

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('staging requires authentication', function () {
    $this->postJson('/fill/stage', ['item_id' => 1])->assertUnauthorized();
});

test('staging stores the item id and its registrable domain for the user', function () {
    $user = User::factory()->create();
    $item = Item::factory()->login(url: 'https://www.example.com/login')->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Example',
    ]);

    $this->actingAs($user)
        ->postJson('/fill/stage', ['item_id' => $item->id])
        ->assertOk()
        ->assertJsonPath('domain', 'example.com');

    expect(Cache::get("fill_stage:{$user->id}"))
        ->toBe(['item_id' => $item->id, 'domain' => 'example.com']);
});

test('staging an item in another user\'s vault is rejected', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $item = Item::factory()->login(url: 'https://example.com')->create([
        'vault_id' => $other->personalVault()->id,
    ]);

    $this->actingAs($user)
        ->postJson('/fill/stage', ['item_id' => $item->id])
        ->assertNotFound();

    expect(Cache::get("fill_stage:{$user->id}"))->toBeNull();
});

test('staging an item without a website is rejected', function () {
    $user = User::factory()->create();
    $item = Item::factory()->login(username: 'jane', password: 'secret')->create([
        'vault_id' => $user->personalVault()->id,
    ]);

    $this->actingAs($user)
        ->postJson('/fill/stage', ['item_id' => $item->id])
        ->assertStatus(422);
});
