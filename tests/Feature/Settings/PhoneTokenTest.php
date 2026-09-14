<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the phone page lists both keys, empty until generated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('phone.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Phone')
            ->where('phone.lookup_endpoint', route('api.lookup'))
            ->has('phone.keys', 2)
            ->where('phone.keys.0.kind', 'device_token')
            ->where('phone.keys.0.token', null)
            ->where('phone.keys.1.kind', 'fill_token')
            ->where('phone.keys.1.token', null)
        );
});

test('generating the device key mints it and it authenticates the lookup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('phone.token.regenerate'), ['kind' => 'device_token'])
        ->assertRedirect(route('phone.edit'));

    $token = $user->fresh()->device_token;
    expect($token)->not->toBeNull()->and(strlen($token))->toBe(48)
        ->and($user->fresh()->fill_token)->toBeNull();

    $this->withHeader('X-Device-Token', $token)
        ->getJson(route('api.lookup', ['url' => 'https://example.com/login']))
        ->assertOk();
});

test('rolling a key invalidates the old one and leaves the other key alone', function () {
    $user = User::factory()->create();
    $oldDevice = $user->regeneratePhoneToken('device_token');
    $fill = $user->regeneratePhoneToken('fill_token');

    $this->actingAs($user)->post(route('phone.token.regenerate'), ['kind' => 'device_token'])->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->device_token)->not->toBe($oldDevice)
        ->and($fresh->fill_token)->toBe($fill);

    $this->withHeader('X-Device-Token', $oldDevice)->getJson(route('api.lookup', ['url' => 'https://example.com']))->assertStatus(401);
    $this->withHeader('X-Device-Token', $fresh->device_token)->getJson(route('api.lookup', ['url' => 'https://example.com']))->assertOk();
});

test('revoking clears just that key', function () {
    $user = User::factory()->create();
    $device = $user->regeneratePhoneToken('device_token');
    $fill = $user->regeneratePhoneToken('fill_token');

    $this->actingAs($user)
        ->delete(route('phone.token.revoke'), ['kind' => 'fill_token'])
        ->assertRedirect(route('phone.edit'));

    $fresh = $user->fresh();
    expect($fresh->fill_token)->toBeNull()->and($fresh->device_token)->toBe($device);
    $this->getJson(route('api.lookup', ['token' => $fill]))->assertStatus(401);
});

test('an unknown key kind is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('phone.edit'))
        ->post(route('phone.token.regenerate'), ['kind' => 'remember_token'])
        ->assertSessionHasErrors('kind');

    expect($user->fresh()->device_token)->toBeNull();
});

test('guests cannot touch the keys', function () {
    $this->get(route('phone.edit'))->assertRedirect(route('login'));
    $this->post(route('phone.token.regenerate'), ['kind' => 'device_token'])->assertRedirect(route('login'));
    $this->delete(route('phone.token.revoke'), ['kind' => 'device_token'])->assertRedirect(route('login'));
});
