<?php

use App\Models\User;
use Laravel\Dusk\Browser;

// Estate standard §6: the notification grant sits at the top of the page.

beforeEach(function () {
    // The 7-day "Not now" lives in localStorage, which outlives a test in
    // Dusk's reused browser session.
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')->script('try { localStorage.clear(); } catch (e) {}');
    });
});

test('a signed-in, unsubscribed device sees the banner until "Not now"', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/vault')
            ->waitFor('@notification-banner')
            ->assertVisible('@notification-banner-dismiss');

        $this->settle($browser)
            ->click('@notification-banner-dismiss')
            ->waitUntilMissing('@notification-banner');

        // Still hidden on the next page load: snoozed on this device.
        $this->settle($browser->visit('/settings/profile'))
            ->assertMissing('@notification-banner');
    });
});

test('on a phone the banner sits in the page flow without sideways scrolling', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user);
        $this->emulateMobileViewport($browser);

        $browser->visit('/vault')->waitFor('@notification-banner');
        $this->settle($browser);

        $position = $browser->script(
            'return getComputedStyle(document.querySelector(\'[data-test="notification-banner"]\')).position;',
        )[0];
        expect($position)->toBe('static');

        $this->assertNoHorizontalOverflow($browser, 'The vault with the notification banner');
    });
});
