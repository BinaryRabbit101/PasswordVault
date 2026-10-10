<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

/*
 * Phones capitalise the first letter of an email field and SQLite `=` is
 * case-sensitive, so every email path must ignore case and stray spaces.
 */
test('users can sign in with a capitalised email', function () {
    $user = User::factory()->create(['email' => 'emily@example.com']);

    $this->post(route('login.store'), [
        'email' => ' Emily@Example.com ',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('stored emails are always lowercase', function () {
    $user = User::factory()->create(['email' => ' Emily@Example.COM ']);

    expect($user->fresh()->email)->toBe('emily@example.com');
});

test('a reset link can be requested with a capitalised email', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
    Notification::fake();

    $user = User::factory()->create(['email' => 'emily@example.com']);

    $this->post(route('password.email'), ['email' => 'Emily@example.com']);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('a password can be reset with a capitalised email', function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
    Notification::fake();

    $user = User::factory()->create(['email' => 'emily@example.com']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => 'Emily@Example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

test('registration rejects an email that differs only by case', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    User::factory()->create(['email' => 'emily@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Emily Again',
        'email' => 'Emily@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
});

test('registration stores a capitalised email in lowercase', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Emily',
        'email' => 'Emily@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::first()->email)->toBe('emily@example.com');
});

test('profile email update is lowercased and unique regardless of case', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'emily@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'Taken@Example.com'])
        ->assertSessionHasErrors('email');

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'Emily.New@Example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('emily.new@example.com');
});
