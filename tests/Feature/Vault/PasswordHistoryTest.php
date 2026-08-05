<?php

use App\Models\Item;
use App\Models\ItemPasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('changing the password records the previous one in history', function () {
    $item = Item::factory()->create(['password' => 'first']);

    $item->update(['password' => 'second']);
    $item->update(['password' => 'third']);

    expect($item->passwordHistories->pluck('password')->all())
        ->toBe(['second', 'first']);
});

test('creating an item does not record any history', function () {
    $item = Item::factory()->create(['password' => 'first']);

    expect($item->passwordHistories)->toBeEmpty();
});

test('updating an item without changing the password does not record history', function () {
    $item = Item::factory()->create(['password' => 'first']);

    $item->update(['notes' => 'changed something else']);

    expect($item->fresh()->passwordHistories)->toBeEmpty();
});

test('password history is encrypted at rest', function () {
    $item = Item::factory()->create(['password' => 'first']);
    $item->update(['password' => 'second']);

    $entry = $item->passwordHistories->first();
    $raw = DB::table('item_password_histories')->where('id', $entry->id)->value('password');

    expect($raw)->not->toContain('first')
        ->and($entry->password)->toBe('first');
});

test('a member can fetch item password history', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => $user->personalVault()->id, 'password' => 'first']);
    $item->update(['password' => 'second']);

    $this->actingAs($user)
        ->getJson(route('items.password-history', $item))
        ->assertOk()
        ->assertJsonPath('history.0.password', 'first')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a non-member cannot fetch item password history', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $item = Item::factory()->create(['vault_id' => $member->personalVault()->id]);

    $this->actingAs($outsider)
        ->getJson(route('items.password-history', $item))
        ->assertForbidden();
});

test('deleting an item deletes its password history', function () {
    $item = Item::factory()->create(['password' => 'first']);
    $item->update(['password' => 'second']);

    $historyId = $item->passwordHistories->first()->id;

    $item->forceDelete();

    expect(ItemPasswordHistory::find($historyId))->toBeNull();
});
