<?php

use App\Models\Item;
use App\Models\User;
use Laravel\Dusk\Browser;

function loginItemFor(User $user, string $name = 'GitHub'): Item
{
    return Item::factory()
        ->login('octo@example.com', 'hunter2', 'https://github.com')
        ->create(['vault_id' => $user->personalVault()->id, 'name' => $name]);
}

test('a password field is masked until revealed; other fields show as-is', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->assertSeeIn('@view-field-0 @view-field-value', 'octo@example.com')
            ->assertSeeIn('@view-field-1 @view-field-value', '••••')
            ->assertDontSeeIn('@view-field-1 @view-field-value', 'hunter2')
            ->assertMissing('@view-field-0 @view-field-reveal')
            ->click('@view-field-1 @view-field-reveal')
            ->assertSeeIn('@view-field-1 @view-field-value', 'hunter2')
            ->assertSeeIn('@view-field-2 @view-field-value', 'https://github.com');
    });
});

test('an item with no filled fields says so', function () {
    $user = User::factory()->create();
    $item = Item::factory()->withFields([])->create(['vault_id' => $user->personalVault()->id]);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)->assertPresent('@no-fields');
    });
});

test('fields can be reordered, removed and added in the edit form', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@item-edit')
            ->waitFor('@field-0')
            ->click('@field-2 @field-up')                 // Website above Password
            ->assertInputValue('@field-1 @field-label', 'Website')
            ->click('@field-0 @field-remove')             // drop Username
            ->assertInputValue('@field-0 @field-label', 'Website')
            ->click('@add-field')
            ->waitFor('@field-2')
            ->type('@field-2 @field-label', 'Recovery code')
            ->select('@field-2 @field-type', 'password')
            ->type('@field-2 @field-value', 'ABCD-1234')
            ->click('@item-save')
            ->waitForText('Item updated.');
    });

    $fresh = $item->fresh();

    expect($fresh->fields->pluck('label')->all())->toBe(['Website', 'Password', 'Recovery code'])
        ->and($fresh->username)->toBeNull()
        ->and($fresh->loginPassword())->toBe('hunter2');
});

test('the generator fills a password field', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@item-edit')
            ->waitFor('@field-1')
            ->click('@field-1 @field-generate')
            ->waitFor('@generator-use');

        $generated = trim($browser->text('@generator-output'));
        expect(strlen($generated))->toBeGreaterThanOrEqual(8);

        $browser->click('@generator-use')
            ->waitUntilMissing('@generator-use')
            ->assertInputValue('@field-1 @field-value', $generated)
            ->assertAttribute('@field-1 @field-value', 'type', 'text')
            ->click('@item-save')
            ->waitForText('Item updated.');

        expect($item->fresh()->loginPassword())->toBe($generated);
    });
});

test('opting a field out of autofill removes it from the list subtitle', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault')
            ->waitFor($this->row($item).' [data-test="item-username"]');

        $this->openItem($browser, $item)
            ->click('@item-edit')
            ->waitFor('@field-0')
            ->select('@field-0 @field-autofill', 'none')
            ->click('@item-save')
            ->waitForText('Item updated.')
            ->waitUntilMissing($this->row($item).' [data-test="item-username"]');
    });

    expect($item->fresh()->username)->toBeNull();
});

test('an item can be deleted after confirming', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@item-delete')
            ->waitForDialog()
            ->acceptDialog()
            ->waitForText('Item deleted.')
            ->assertMissing($this->row($item));
    });

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});

test('every field has a copy button on its left, and its value is plain selectable text', function () {
    $user = User::factory()->create();
    $item = loginItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->assertPresent('@view-field-0 @view-field-copy')
            ->assertPresent('@view-field-2 @view-field-copy')
            ->click('@view-field-1 @view-field-copy')
            ->waitForText('Password copied');

        // The value itself is text to highlight, not a button, and the copy
        // button sits before it.
        [$tag, $copyFirst] = $browser->script([
            'return document.querySelector(\'[data-test="view-field-0"] [data-test="view-field-value"]\').tagName;',
            'const c = document.querySelector(\'[data-test="view-field-0"] [data-test="view-field-copy"]\');'
            .'const v = document.querySelector(\'[data-test="view-field-0"] [data-test="view-field-value"]\');'
            .'return c.getBoundingClientRect().left < v.getBoundingClientRect().left;',
        ]);

        expect($tag)->toBe('SPAN')->and($copyFirst)->toBeTrue();

        $browser->click('@item-edit')
            ->waitFor('@field-0')
            ->click('@field-0 @field-copy')
            ->waitForText('Username copied');
    });
});
