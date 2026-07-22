<?php

use App\Models\Item;
use App\Models\User;
use App\Services\Import\LastPassCsvParser;
use App\Services\Import\LastPassImporter;

function fixtureCsv(): string
{
    return file_get_contents(base_path('tests/Fixtures/lastpass-export.csv'));
}

test('the parser handles multiline notes, commas, quotes, totp and secure notes', function () {
    $result = app(LastPassCsvParser::class)->parse(fixtureCsv());

    expect($result->rows)->toHaveCount(4)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['reason'])->toBe('Row has no name or URL.');

    [$example, $bank, $wifi, $noName] = $result->rows->all();

    expect($example->name)->toBe('Example')
        ->and($example->favorite)->toBeFalse();

    expect($bank->name)->toBe('My Bank')
        ->and($bank->password)->toBe('p@ss,word')
        ->and($bank->totp)->toBe('JBSWY3DPEHPK3PXP')
        ->and($bank->notes)->toContain("Multi-line\nnote")
        ->and($bank->notes)->toContain('"quotes"')
        ->and($bank->grouping)->toBe('Finance\Banks')
        ->and($bank->favorite)->toBeTrue();

    // LastPass secure notes use the sentinel URL http://sn.
    expect($wifi->url)->toBeNull()
        ->and($wifi->name)->toBe('Home WiFi')
        ->and($wifi->notes)->toBe('The wifi password is on the router.');

    // A row without a name falls back to the URL host.
    expect($noName->name)->toBe('noname.test');
});

test('a parser rejects content that is not a LastPass export', function () {
    $result = app(LastPassCsvParser::class)->parse("just,some,random\ncsv,data,here");

    expect($result->rows)->toBeEmpty()
        ->and($result->errors[0]['reason'])->toContain('LastPass');
});

test('importing creates items and folders; re-importing skips everything', function () {
    $user = User::factory()->create();
    $vault = $user->personalVault();
    $parsed = app(LastPassCsvParser::class)->parse(fixtureCsv());

    $first = app(LastPassImporter::class)->import($parsed->rows, $vault);

    expect($first->imported)->toBe(4)
        ->and($first->duplicates)->toBe(0)
        ->and($vault->folders()->pluck('name')->all())
        ->toContain('Sites', 'Finance\Banks', 'Notes');

    $bank = Item::firstWhere('name', 'My Bank');
    expect($bank->password)->toBe('p@ss,word')
        ->and($bank->totp_secret)->toBe('JBSWY3DPEHPK3PXP')
        ->and($bank->favorite)->toBeTrue()
        ->and($bank->folder->name)->toBe('Finance\Banks');

    $second = app(LastPassImporter::class)->import($parsed->rows, $vault);

    expect($second->imported)->toBe(0)
        ->and($second->duplicates)->toBe(4)
        ->and($vault->items()->count())->toBe(4);
});

test('the import wizard flow works end to end', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('vault.import.preview'), ['csv' => fixtureCsv()])
        ->assertRedirect(route('vault.import.create'));

    $this->actingAs($user)
        ->get(route('vault.import.create'))
        ->assertInertia(fn ($page) => $page
            ->component('vault/Import')
            ->where('preview.total', 4)
            ->has('preview.rows', 4)
            ->has('preview.importId'));

    $importId = session('vault_import_id');

    $this->actingAs($user)
        ->post(route('vault.import.store'), [
            'import_id' => $importId,
            'vault_id' => $user->personalVault()->id,
        ])
        ->assertRedirect(route('vault.index'));

    expect($user->personalVault()->items()->count())->toBe(4);
});

test('the cached import csv is encrypted, not plaintext', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('vault.import.preview'), ['csv' => fixtureCsv()]);

    $importId = session('vault_import_id');
    $cached = cache()->get("vault-import:{$importId}");

    expect($cached)->toBeString()
        ->and($cached)->not->toContain('hunter2')
        ->and(\Illuminate\Support\Facades\Crypt::decryptString($cached))->toContain('hunter2');
});
