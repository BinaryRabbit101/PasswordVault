<?php

test('requests from allowed networks pass', function () {
    $this->get('/')->assertOk();
});

test('requests from outside the allowed CIDRs are rejected', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->get('/')
        ->assertForbidden();
});

test('the api is also gated by network', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson('/api/lookup?q=test')
        ->assertForbidden();
});

test('an empty allow-list rejects everything', function () {
    config(['vault.network.allowed_cidrs' => []]);

    $this->get('/')->assertForbidden();
});
