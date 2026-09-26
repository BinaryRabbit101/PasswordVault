<?php

use App\Models\Item;
use App\Models\User;
use Laravel\Dusk\Browser;

test('an empty vault says so', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/vault')
            ->waitFor('@vault-empty')
            ->assertSeeIn('@vault-empty', 'Your vault is empty');
    });
});

test('the list shows each item with its username and filters as you search', function () {
    $user = User::factory()->create();
    $vault = $user->personalVault()->id;

    $github = Item::factory()->login('octo@example.com', 'hunter2', 'https://github.com')
        ->create(['vault_id' => $vault, 'name' => 'GitHub']);
    $bot = Item::factory()->withFields([
        ['label' => 'Bot token', 'type' => 'password', 'value' => 'MTIz.abc', 'autofill' => 'none'],
    ])->create(['vault_id' => $vault, 'name' => 'Discord bot']);

    $this->browse(function (Browser $browser) use ($user, $github, $bot) {
        $browser->loginAs($user)
            ->visit('/vault')
            ->waitFor($this->row($github))
            ->assertSeeIn($this->row($github).' [data-test="item-username"]', 'octo@example.com')
            ->assertPresent($this->row($bot))
            ->type('@vault-search', 'disc')
            ->waitUntilMissing($this->row($github))
            ->assertPresent($this->row($bot))
            ->clear('@vault-search')
            ->type('@vault-search', 'nothing matches this')
            ->waitFor('@vault-empty')
            ->assertSeeIn('@vault-empty', 'No items match your search');
    });
});

test('copy-password only shows on items that have a password to fill', function () {
    $user = User::factory()->create();
    $vault = $user->personalVault()->id;

    $login = Item::factory()->login('jane', 'hunter2')->create(['vault_id' => $vault, 'name' => 'Login']);
    $bot = Item::factory()->withFields([
        ['label' => 'Bot token', 'type' => 'password', 'value' => 'MTIz.abc', 'autofill' => 'none'],
        ['label' => 'Application ID', 'type' => 'text', 'value' => '1234567890', 'autofill' => 'none'],
    ])->create(['vault_id' => $vault, 'name' => 'Bot']);

    $this->browse(function (Browser $browser) use ($user, $login, $bot) {
        $browser->loginAs($user)
            ->visit('/vault')
            ->waitFor($this->row($login))
            ->assertPresent($this->row($login).' [data-test="item-copy-password"]')
            ->assertMissing($this->row($bot).' [data-test="item-copy-password"]')
            ->assertMissing($this->row($bot).' [data-test="item-copy-username"]')
            ->assertMissing($this->row($bot).' [data-test="item-username"]');
    });
});

test('the list and the new-item sheet fit a phone screen', function () {
    $user = User::factory()->create();
    Item::factory()->login('a-rather-long-username@a-rather-long-domain.example', 'pw', 'https://example.com')
        ->create(['vault_id' => $user->personalVault()->id, 'name' => 'An item with a fairly long name to wrap']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user);
        $this->emulateMobileViewport($browser);

        $browser->visit('/vault')->waitFor('@item-row');
        $this->assertNoHorizontalOverflow($browser, 'The vault list');

        $this->openNewItem($browser);
        $this->assertNoHorizontalOverflow($browser, 'The new-item sheet');
    });
});
