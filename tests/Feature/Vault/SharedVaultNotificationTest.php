<?php

use App\Models\Item;
use App\Models\User;
use App\Models\Vault;
use App\Notifications\SharedItemChangedNotification;
use App\Services\Import\LastPassCsvParser;
use App\Services\Import\LastPassImporter;
use Illuminate\Support\Facades\Notification;

test('changing a shared item notifies the other members only', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $partner = User::factory()->create();
    $shared = Vault::factory()->shared()->create();
    $actor->vaults()->attach($shared);
    $partner->vaults()->attach($shared);

    $this->actingAs($actor)->post(route('items.store'), [
        'vault_id' => $shared->id,
        'name' => 'Streaming Login',
    ]);

    Notification::assertSentTo($partner, SharedItemChangedNotification::class);
    Notification::assertNotSentTo($actor, SharedItemChangedNotification::class);
});

test('personal vault changes do not notify anyone', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('items.store'), [
        'vault_id' => $user->personalVault()->id,
        'name' => 'Private thing',
    ]);

    Notification::assertNothingSent();
});

test('bulk imports into a shared vault do not flood notifications', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $partner = User::factory()->create();
    $shared = Vault::factory()->shared()->create();
    $actor->vaults()->attach($shared);
    $partner->vaults()->attach($shared);

    $this->actingAs($actor);

    $parsed = app(LastPassCsvParser::class)->parse(
        file_get_contents(base_path('tests/Fixtures/lastpass-export.csv')),
    );
    app(LastPassImporter::class)->import($parsed->rows, $shared);

    Notification::assertNothingSent();
    expect($shared->items()->count())->toBe(4);
});
