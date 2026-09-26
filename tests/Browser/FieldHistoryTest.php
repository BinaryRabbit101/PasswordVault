<?php

use App\Models\Item;
use App\Models\User;
use Laravel\Dusk\Browser;

test('resetting a token keeps the old one in that field\'s history', function () {
    $user = User::factory()->create();
    $item = Item::factory()->withFields([
        ['label' => 'Bot token', 'type' => 'password', 'value' => 'first-token', 'autofill' => 'none'],
    ])->create(['vault_id' => $user->personalVault()->id, 'name' => 'Discord bot']);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        foreach (['second-token', 'third-token'] as $token) {
            $this->openItem($browser, $item)
                ->click('@item-edit')
                ->waitFor('@field-0')
                ->clear('@field-0 @field-value')
                ->type('@field-0 @field-value', $token)
                ->click('@item-save')
                ->waitForText('Item updated.')
                ->waitUntilMissing('@item-save');
        }

        $this->openItem($browser, $item)
            ->click('@view-field-0 @view-field-history')
            ->waitFor('@history-entry')
            ->assertCount('@history-entry', 2)
            ->assertSeeIn('@history-entry @history-value', '••••')
            ->click('@history-entry @history-reveal')
            ->assertSeeIn('@history-entry @history-value', 'second-token');
    });
});

test('a field that never changed has no history', function () {
    $user = User::factory()->create();
    $item = Item::factory()->login('octo@example.com', 'hunter2')
        ->create(['vault_id' => $user->personalVault()->id]);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@view-field-0 @view-field-history')
            ->waitFor('@history-empty')
            ->assertSeeIn('@history-empty', 'No previous values recorded');
    });
});

test('text fields show their old values without a reveal step', function () {
    $user = User::factory()->create();
    $item = Item::factory()->login('old-name')->create(['vault_id' => $user->personalVault()->id]);
    $item->fields->first()->update(['value' => 'new-name']);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@view-field-0 @view-field-history')
            ->waitFor('@history-entry')
            ->assertSeeIn('@history-entry @history-value', 'old-name')
            ->assertMissing('@history-reveal');
    });
});
