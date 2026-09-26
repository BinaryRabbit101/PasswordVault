<?php

use App\Models\Item;
use App\Models\ItemField;
use App\Models\ItemFieldHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function tokenItem(): Item
{
    return Item::factory()->withFields([
        ['label' => 'Bot token', 'type' => 'password', 'value' => 'first', 'autofill' => 'none'],
    ])->create();
}

test('changing any field records its previous value', function () {
    $field = tokenItem()->fields->first();

    $field->update(['value' => 'second']);
    $field->update(['value' => 'third']);

    expect($field->histories->pluck('value')->all())->toBe(['second', 'first']);
});

test('text fields keep history too', function () {
    $item = Item::factory()->login('old-name')->create();
    $field = $item->fields->first();

    $field->update(['value' => 'new-name']);

    expect($field->histories->pluck('value')->all())->toBe(['old-name']);
});

test('only the last ten previous values are kept per field', function () {
    $field = tokenItem()->fields->first();

    foreach (range(1, 12) as $n) {
        $field->update(['value' => "v{$n}"]);
    }

    $values = $field->histories()->pluck('value')->all();

    expect($values)->toHaveCount(ItemField::HISTORY_LIMIT)
        ->and($values[0])->toBe('v11')
        ->and($values)->not->toContain('first', 'v1');
});

test('relabelling a field without changing its value records nothing', function () {
    $field = tokenItem()->fields->first();

    $field->update(['label' => 'Reset token']);

    expect($field->histories)->toBeEmpty();
});

test('history survives an edit through the form', function () {
    $user = User::factory()->create();
    $item = Item::factory()->withFields([
        ['label' => 'Bot token', 'type' => 'password', 'value' => 'first', 'autofill' => 'none'],
    ])->create(['vault_id' => $user->personalVault()->id]);
    $field = $item->fields->first();

    $this->actingAs($user)->put(route('items.update', $item), [
        'name' => $item->name,
        'fields' => [['id' => $field->id, 'label' => 'Bot token', 'type' => 'password', 'value' => 'second', 'autofill' => 'none']],
    ])->assertRedirect();

    $this->actingAs($user)
        ->getJson(route('items.fields.history', [$item, $field]))
        ->assertOk()
        ->assertJsonPath('history.0.value', 'first')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('field history is encrypted at rest', function () {
    $field = tokenItem()->fields->first();
    $field->update(['value' => 'second']);

    $raw = DB::table('item_field_histories')->where('item_field_id', $field->id)->value('value');

    expect($raw)->not->toContain('first');
});

test('a non-member cannot fetch field history', function () {
    $outsider = User::factory()->create();
    $item = tokenItem();

    $this->actingAs($outsider)
        ->getJson(route('items.fields.history', [$item, $item->fields->first()]))
        ->assertForbidden();
});

test('a field is only reachable through its own item', function () {
    $user = User::factory()->create();
    $mine = Item::factory()->create(['vault_id' => $user->personalVault()->id]);
    $theirs = tokenItem();

    $this->actingAs($user)
        ->getJson(route('items.fields.history', [$mine, $theirs->fields->first()]))
        ->assertNotFound();
});

test('deleting an item deletes its field history', function () {
    $item = tokenItem();
    $item->fields->first()->update(['value' => 'second']);

    $item->forceDelete();

    expect(ItemFieldHistory::count())->toBe(0);
});
