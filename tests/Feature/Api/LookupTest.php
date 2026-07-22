<?php

use App\Models\Item;
use App\Models\User;
use App\Support\Totp;

test('lookup requires a valid device token', function () {
    $this->getJson('/api/lookup?q=test')->assertUnauthorized();

    $this->getJson('/api/lookup?q=test', ['X-Device-Token' => 'wrong'])
        ->assertUnauthorized();
});

test('lookup matches items by url host and returns the current totp code', function () {
    $user = User::factory()->create();
    $user->forceFill(['device_token' => str_repeat('a', 48)])->save();

    Item::factory()->withTotp()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Example',
        'url' => 'https://www.example.com/login',
        'username' => 'jane',
        'password' => 'hunter2',
    ]);

    $response = $this->getJson(
        '/api/lookup?url=https://example.com/session/new',
        ['X-Device-Token' => str_repeat('a', 48)],
    );

    $response->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.name', 'Example')
        ->assertJsonPath('matches.0.username', 'jane')
        ->assertJsonPath('matches.0.password', 'hunter2')
        ->assertJsonPath('matches.0.totp', Totp::code('JBSWY3DPEHPK3PXP'));
});

test('lookup only sees vaults the token owner belongs to', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $owner->forceFill(['device_token' => str_repeat('b', 48)])->save();

    Item::factory()->create([
        'vault_id' => $other->personalVault()->id,
        'name' => 'Private',
        'url' => 'https://private.test',
    ]);

    $this->getJson('/api/lookup?url=https://private.test', ['X-Device-Token' => str_repeat('b', 48)])
        ->assertOk()
        ->assertJsonCount(0, 'matches');
});

test('passwords are omitted when the config flag is off', function () {
    config(['vault.api_returns_passwords' => false]);

    $user = User::factory()->create();
    $user->forceFill(['device_token' => str_repeat('c', 48)])->save();

    Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Example',
        'url' => 'https://example.com',
        'password' => 'hunter2',
    ]);

    $this->getJson('/api/lookup?q=example', ['X-Device-Token' => str_repeat('c', 48)])
        ->assertOk()
        ->assertJsonPath('matches.0.name', 'Example')
        ->assertJsonMissingPath('matches.0.password');
});
