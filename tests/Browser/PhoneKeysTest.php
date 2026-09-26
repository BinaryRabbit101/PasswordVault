<?php

use App\Models\User;
use Laravel\Dusk\Browser;

test('a device key can be generated and is shown on the page', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/settings/phone')
            ->waitFor('@phone-key-panel')
            ->assertPresent('@no-device_token')
            ->click('@regenerate-device_token')
            ->waitFor('@token-device_token');

        expect(trim($browser->text('@token-device_token')))->toBe($user->fresh()->device_token);
    });
});

test('rolling a key asks first, then replaces it', function () {
    $user = User::factory()->create();
    $user->regeneratePhoneToken('fill_token');
    $original = $user->fresh()->fill_token;

    $this->browse(function (Browser $browser) use ($user, $original) {
        $browser->loginAs($user)
            ->visit('/settings/phone')
            ->waitFor('@token-fill_token')
            ->click('@regenerate-fill_token')
            ->waitForDialog()
            ->dismissDialog()
            ->assertSeeIn('@token-fill_token', $original)
            ->click('@regenerate-fill_token')
            ->waitForDialog()
            ->acceptDialog()
            ->waitUntilMissingText($original);

        $rolled = $user->fresh()->fill_token;

        expect($rolled)->not->toBe($original)
            ->and(trim($browser->text('@token-fill_token')))->toBe($rolled);
    });
});

test('revoking a key clears it', function () {
    $user = User::factory()->create();
    $user->regeneratePhoneToken('device_token');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/settings/phone')
            ->waitFor('@token-device_token')
            ->click('@revoke-device_token')
            ->waitForDialog()
            ->acceptDialog()
            ->waitFor('@no-device_token');
    });

    expect($user->fresh()->device_token)->toBeNull();
});
