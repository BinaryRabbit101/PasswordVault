<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('vault.index'));
    $response->assertRedirect(route('login'));
});

test('the dashboard route redirects to the vault', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/vault');
});

test('authenticated users can visit the vault', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('vault.index'));
    $response->assertOk();
});
