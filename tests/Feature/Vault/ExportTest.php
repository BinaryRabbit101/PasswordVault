<?php

use App\Models\Item;
use App\Models\User;
use App\Services\Import\LastPassCsvParser;

test('export requires password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('vault.export'))
        ->assertRedirect();
});

test('exported csv round-trips through the parser', function () {
    $user = User::factory()->create();
    $vault = $user->personalVault();

    $item = Item::factory()->withTotp()->favorite()->create([
        'vault_id' => $vault->id,
        'name' => 'Round Trip',
        'url' => 'https://roundtrip.test',
        'username' => 'jane',
        'password' => "tricky,\"password\"\nwith newline",
        'notes' => "line one\nline two",
    ]);
    $item->fields()->create([
        'label' => 'PIN', 'type' => 'password', 'value' => '9876',
        'is_secret' => true, 'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('vault.export'));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Disposition', 'attachment; filename="vault-export.csv"');

    $parsed = app(LastPassCsvParser::class)->parse($response->getContent());

    expect($parsed->errors)->toBeEmpty()
        ->and($parsed->rows)->toHaveCount(1);

    $row = $parsed->rows->first();

    expect($row->name)->toBe('Round Trip')
        ->and($row->username)->toBe('jane')
        ->and($row->password)->toBe("tricky,\"password\"\nwith newline")
        ->and($row->totp)->toBe('JBSWY3DPEHPK3PXP')
        ->and($row->favorite)->toBeTrue()
        ->and($row->notes)->toContain('line two')
        ->and($row->notes)->toContain('PIN: 9876');
});
