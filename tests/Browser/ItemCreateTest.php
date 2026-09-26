<?php

use App\Models\Item;
use App\Models\User;
use Laravel\Dusk\Browser;

test('a new item starts as a login and saves username, password and website', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->assertInputValue('@field-0 @field-label', 'Username')
            ->assertInputValue('@field-1 @field-label', 'Password')
            ->assertInputValue('@field-2 @field-label', 'Website')
            ->type('@item-name', 'GitHub')
            ->type('@field-0 @field-value', 'octo@example.com')
            ->type('@field-1 @field-value', 'hunter2')
            ->assertAttribute('@field-1 @field-value', 'type', 'password')
            ->type('@field-2 @field-value', 'https://github.com')
            ->click('@item-save')
            ->waitForText('Item added.')
            ->waitFor('@item-row')
            ->assertSeeIn('@item-row', 'octo@example.com');
    });

    $item = Item::firstWhere('name', 'GitHub');

    expect($item->username)->toBe('octo@example.com')
        ->and($item->loginPassword())->toBe('hunter2')
        ->and($item->url)->toBe('https://github.com');
});

test('an API credential needs no username or password', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->click('@template-api-credential')
            ->assertMissing('@field-2')
            ->type('@item-name', 'Discord bot')
            ->clear('@field-0 @field-label')
            ->type('@field-0 @field-label', 'Bot token')
            ->type('@field-0 @field-value', 'MTIz.first.token')
            ->select('@field-0 @field-autofill', 'none')
            ->type('@field-1 @field-value', 'https://discord.com/developers/applications')
            ->click('@add-field')
            ->waitFor('@field-2')
            ->type('@field-2 @field-label', 'Application ID')
            ->type('@field-2 @field-value', '1234567890')
            ->select('@field-2 @field-autofill', 'none')
            ->click('@item-save')
            ->waitForText('Item added.')
            ->waitFor('@item-row')
            ->assertMissing('[data-test="item-copy-password"]')
            ->assertMissing('[data-test="item-username"]');
    });

    $item = Item::firstWhere('name', 'Discord bot');

    expect($item->fields->pluck('label')->all())->toBe(['Bot token', 'Website', 'Application ID'])
        ->and($item->fields->first()->value)->toBe('MTIz.first.token')
        ->and($item->username)->toBeNull()
        ->and($item->loginPassword())->toBeNull();
});

test('a secure note is a single note field', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->click('@template-secure-note')
            ->assertMissing('@field-1')
            ->type('@item-name', 'Wifi')
            ->type('@field-0 @field-value', "Network: home\nKey on the router")
            ->click('@item-save')
            ->waitForText('Item added.');

        $item = Item::firstWhere('name', 'Wifi');

        $this->openItem($browser, $item)
            ->assertAttribute('@view-field-0', 'data-field-type', 'note')
            ->assertSeeIn('@view-field-0 @view-field-value', 'Key on the router');
    });
});

test('the blank template starts with no fields', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->click('@template-blank')
            ->assertMissing('@field-0')
            ->type('@item-name', 'Placeholder')
            ->click('@item-save')
            ->waitForText('Item added.');
    });

    expect(Item::firstWhere('name', 'Placeholder')->fields)->toBeEmpty();
});

test('switching template after typing asks first and can be cancelled', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->type('@field-0 @field-value', 'typed already')
            ->click('@template-blank')
            ->waitForDialog()
            ->dismissDialog()
            ->assertInputValue('@field-0 @field-value', 'typed already')
            ->click('@template-blank')
            ->waitForDialog()
            ->acceptDialog()
            ->waitUntilMissing('@field-0');
    });
});

test('a bad one-time-code secret is shown on its field', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))
            ->click('@template-blank')
            ->type('@item-name', 'Bad 2FA')
            ->click('@add-field')
            ->waitFor('@field-0')
            ->type('@field-0 @field-label', '2FA')
            ->select('@field-0 @field-type', 'totp')
            ->type('@field-0 @field-value', 'not!valid@base32')
            ->click('@item-save')
            ->waitFor('@field-0 @field-error')
            ->assertSeeIn('@field-0 @field-error', 'base32');
    });

    expect(Item::firstWhere('name', 'Bad 2FA'))->toBeNull();
});
