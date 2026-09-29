<?php

use App\Models\Item;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

/**
 * A real mouse click on the backdrop's top-left corner (the sheet is anchored to
 * the bottom). Reka dismisses on pointerdown, which clickAtPoint's JS click skips.
 */
function clickBackdrop(Browser $browser): Browser
{
    $overlay = $browser->driver->findElement(WebDriverBy::cssSelector('[data-slot="sheet-overlay"]'));
    $size = $overlay->getSize();

    $browser->driver->action()
        ->moveToElement($overlay, (int) (10 - $size->getWidth() / 2), (int) (10 - $size->getHeight() / 2))
        ->click()
        ->perform();

    return $browser->pause(400);
}

function dismissItemFor(User $user): Item
{
    return Item::factory()
        ->login('octo@example.com', 'hunter2', 'https://github.com')
        ->create(['vault_id' => $user->personalVault()->id, 'name' => 'GitHub']);
}

test('clicking away does not close a new item', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $this->openNewItem($browser->loginAs($user))->type('@item-name', 'Half typed');

        clickBackdrop($browser)
            ->assertVisible('@item-name')
            ->assertInputValue('@item-name', 'Half typed');
    });
});

test('clicking away does not close an item being edited', function () {
    $user = User::factory()->create();
    $item = dismissItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item)
            ->click('@item-edit')
            ->waitFor('@field-0')
            ->type('@field-0 @field-value', 'work@example.com');

        clickBackdrop($browser)
            ->assertVisible('@field-0')
            ->assertInputValue('@field-0 @field-value', 'work@example.com');
    });
});

test('clicking away still closes an item that is only being viewed', function () {
    $user = User::factory()->create();
    $item = dismissItemFor($user);

    $this->browse(function (Browser $browser) use ($user, $item) {
        $browser->loginAs($user)->visit('/vault');

        $this->openItem($browser, $item);

        clickBackdrop($browser)->waitUntilMissing('@item-edit');
    });
});
