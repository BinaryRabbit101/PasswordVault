<?php

use App\Models\User;
use Laravel\Dusk\Browser;

// Sign-in tests type real passwords, so each starts in a fresh browser: after
// a successful password sign-in Chrome keeps keyboard focus on its own "save
// password?" prompt, and a later test's keystrokes never reach the page.
beforeEach(fn () => static::closeAll());

test('a guest opening the vault is sent to sign in', function () {
    $this->browse(function (Browser $browser) {
        $browser->logout()
            ->visit('/vault')
            ->assertPathIs('/login');
    });
});

test('signing in with the right password lands on the vault', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->browse(function (Browser $browser) {
        $this->settle($browser->logout()->visit('/login'))
            ->type('#email', 'jane@example.com')
            ->type('#password', 'password')
            ->click('@login-button')
            ->waitForLocation('/vault')
            ->assertPresent('@vault-search');
    });

    expect($user->fresh())->not->toBeNull();
});

test('a wrong password is refused', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->browse(function (Browser $browser) {
        $this->settle($browser->logout()->visit('/login'))
            ->type('#email', 'jane@example.com')
            ->type('#password', 'not-the-password')
            ->click('@login-button')
            ->waitForText('These credentials do not match')
            ->assertPathIs('/login');
    });
});

test('signing in with a phone-capitalised email still lands on the vault', function () {
    User::factory()->create(['email' => 'kim@example.com']);

    $this->browse(function (Browser $browser) {
        $this->settle($browser->logout()->visit('/login'))
            ->assertAttribute('#email', 'autocapitalize', 'none')
            ->assertAttribute('#email', 'autocomplete', 'email')
            ->type('#email', 'Kim@Example.com')
            ->type('#password', 'password')
            ->click('@login-button')
            ->waitForLocation('/vault')
            ->assertPresent('@vault-search');
    });
});
