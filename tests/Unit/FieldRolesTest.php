<?php

use App\Support\FieldRoles;

test('auto picks the first filled text or email field and the first password field', function () {
    $fields = [
        ['type' => 'url', 'value' => 'https://example.com'],
        ['type' => 'text', 'value' => ''],
        ['type' => 'email', 'value' => 'jane@example.com'],
        ['type' => 'password', 'value' => 'hunter2'],
        ['type' => 'password', 'value' => 'pin'],
    ];

    expect(FieldRoles::username($fields))->toBe('jane@example.com')
        ->and(FieldRoles::password($fields))->toBe('hunter2');
});

test('an explicit autofill choice beats auto', function () {
    $fields = [
        ['type' => 'text', 'value' => 'display name'],
        ['type' => 'password', 'value' => 'old'],
        ['type' => 'text', 'value' => 'login-id', 'autofill' => 'username'],
        ['type' => 'password', 'value' => 'real', 'autofill' => 'password'],
    ];

    expect(FieldRoles::username($fields))->toBe('login-id')
        ->and(FieldRoles::password($fields))->toBe('real');
});

test('fields opted out are never picked', function () {
    $fields = [
        ['type' => 'text', 'value' => '1234567890', 'autofill' => 'none'],
        ['type' => 'password', 'value' => 'bot-token', 'autofill' => 'none'],
    ];

    expect(FieldRoles::username($fields))->toBeNull()
        ->and(FieldRoles::password($fields))->toBeNull();
});

test('urls and totp come from their field types', function () {
    $fields = [
        ['type' => 'url', 'value' => 'https://a.test'],
        ['type' => 'totp', 'value' => 'JBSWY3DPEHPK3PXP'],
        ['type' => 'url', 'value' => 'https://b.test'],
    ];

    expect(FieldRoles::urls($fields))->toBe(['https://a.test', 'https://b.test'])
        ->and(FieldRoles::totp($fields))->toBe('JBSWY3DPEHPK3PXP');
});

test('the role labels name the same fields the values come from', function () {
    $fields = [
        ['label' => 'Display name', 'type' => 'text', 'value' => ''],
        ['label' => 'Client ID', 'type' => 'text', 'value' => 'abc'],
        ['label' => 'Old key', 'type' => 'password', 'value' => 'old'],
        ['label' => 'API token', 'type' => 'password', 'value' => 'tok', 'autofill' => 'password'],
    ];

    expect(FieldRoles::usernameLabel($fields))->toBe('Client ID')
        ->and(FieldRoles::passwordLabel($fields))->toBe('API token')
        ->and(FieldRoles::passwordLabel([['label' => 'Bot token', 'type' => 'password', 'value' => 'x', 'autofill' => 'none']]))->toBeNull();
});
