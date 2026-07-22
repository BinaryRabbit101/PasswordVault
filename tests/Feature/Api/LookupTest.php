<?php

use App\Models\Item;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Cache;

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

test('fill token matches the request Origin across subdomains', function () {
    $user = User::factory()->create();
    $user->forceFill(['fill_token' => str_repeat('d', 48)])->save();

    Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Example',
        'url' => 'https://www.example.com/login',
        'username' => 'jane',
        'password' => 'hunter2',
    ]);

    // Origin is a login subdomain; the stored url is the bare domain.
    $this->getJson('/api/lookup?token='.str_repeat('d', 48), ['Origin' => 'https://accounts.example.com'])
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.username', 'jane')
        ->assertJsonPath('matches.0.password', 'hunter2');
});

test('fill token ignores a caller-supplied url and scopes to the Origin', function () {
    $user = User::factory()->create();
    $user->forceFill(['fill_token' => str_repeat('e', 48)])->save();

    Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Bank',
        'url' => 'https://bank.example/login',
        'password' => 'secret',
    ]);
    Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Mail',
        'url' => 'https://mail.test/login',
        'password' => 'other',
    ]);

    // Attacker-controlled page is mail.test but tries to pull bank.example.
    $this->getJson('/api/lookup?token='.str_repeat('e', 48).'&url=https://bank.example', ['Origin' => 'https://mail.test'])
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.name', 'Mail');
});

test('fill token without an Origin header is rejected', function () {
    $user = User::factory()->create();
    $user->forceFill(['fill_token' => str_repeat('f', 48)])->save();

    $this->getJson('/api/lookup?token='.str_repeat('f', 48))
        ->assertStatus(422);
});

test('a staged item is returned exactly and consumed on first use', function () {
    $user = User::factory()->create();
    $user->forceFill(['fill_token' => str_repeat('g', 48)])->save();

    $picked = Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Work Google',
        'url' => 'https://google.com',
        'username' => 'work@example.com',
    ]);
    // Second account on the same domain — an Origin-only match is ambiguous.
    Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'name' => 'Personal Google',
        'url' => 'https://google.com',
        'username' => 'me@example.com',
    ]);

    Cache::put("fill_stage:{$user->id}", ['item_id' => $picked->id, 'domain' => 'google.com'], now()->addMinute());

    $this->getJson('/api/lookup?token='.str_repeat('g', 48), ['Origin' => 'https://accounts.google.com'])
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.username', 'work@example.com');

    // Consumed — the next run falls back to the ambiguous two-account search.
    expect(Cache::get("fill_stage:{$user->id}"))->toBeNull();

    $this->getJson('/api/lookup?token='.str_repeat('g', 48), ['Origin' => 'https://accounts.google.com'])
        ->assertOk()
        ->assertJsonCount(2, 'matches');
});

test('a staged item is ignored (and left intact) when the page domain differs', function () {
    $user = User::factory()->create();
    $user->forceFill(['fill_token' => str_repeat('h', 48)])->save();

    $bank = Item::factory()->create([
        'vault_id' => $user->personalVault()->id,
        'url' => 'https://bank.example',
    ]);

    Cache::put("fill_stage:{$user->id}", ['item_id' => $bank->id, 'domain' => 'bank.example'], now()->addMinute());

    $this->getJson('/api/lookup?token='.str_repeat('h', 48), ['Origin' => 'https://mail.test'])
        ->assertOk()
        ->assertJsonCount(0, 'matches');

    expect(Cache::get("fill_stage:{$user->id}"))->not->toBeNull();
});
