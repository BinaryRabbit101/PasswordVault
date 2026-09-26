<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items become name + folder + favorite + fields. The fixed username,
 * password, website, TOTP and notes columns turn into ordinary fields, and
 * password history becomes per-field history.
 *
 * Values are moved as ciphertext (same cast, same key), never decrypted.
 * Existing text/email custom fields that were marked "hide until tapped"
 * become password fields, since concealment is now the password type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_fields', function (Blueprint $table) {
            $table->string('autofill', 16)->nullable()->after('type');
        });

        Schema::create('item_field_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_field_id')->constrained()->cascadeOnDelete();

            // Encrypted cast — ciphertext is ~3x plaintext, so a text column.
            $table->text('value');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_field_id', 'created_at']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->text('urls')->nullable()->after('url');
        });

        DB::table('item_fields')
            ->where('is_secret', true)
            ->whereIn('type', ['text', 'email'])
            ->update(['type' => 'password']);

        DB::table('items')->orderBy('id')->each(function (stdClass $item): void {
            $this->moveItem($item);
        });

        Schema::table('item_fields', function (Blueprint $table) {
            $table->dropColumn('is_secret');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['password', 'notes', 'totp_secret', 'password_updated_at']);
        });

        Schema::dropIfExists('item_password_histories');
    }

    private function moveItem(stdClass $item): void
    {
        $now = now();
        $leading = array_filter([
            ['label' => 'Username', 'type' => 'text', 'value' => $item->username],
            ['label' => 'Password', 'type' => 'password', 'value' => $item->password],
            ['label' => 'Website', 'type' => 'url', 'value' => $item->url === null ? null : encrypt($item->url, false)],
            ['label' => 'One-time code', 'type' => 'totp', 'value' => $item->totp_secret],
        ], fn (array $field) => $item->{$this->columnFor($field['type'])} !== null);

        // Existing custom fields keep their order, after the moved ones.
        DB::table('item_fields')
            ->where('item_id', $item->id)
            ->update(['sort_order' => DB::raw('sort_order + '.count($leading))]);

        $order = 0;

        foreach ($leading as $field) {
            $fieldId = DB::table('item_fields')->insertGetId([
                ...$field,
                'item_id' => $item->id,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($field['type'] === 'password') {
                $history = DB::table('item_password_histories')
                    ->where('item_id', $item->id)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->get(['password', 'created_at']);

                foreach ($history as $entry) {
                    DB::table('item_field_histories')->insert([
                        'item_field_id' => $fieldId,
                        'value' => $entry->password,
                        'created_at' => $entry->created_at,
                    ]);
                }
            }
        }

        if ($item->notes !== null) {
            $last = (int) DB::table('item_fields')->where('item_id', $item->id)->max('sort_order');

            DB::table('item_fields')->insert([
                'item_id' => $item->id,
                'label' => 'Notes',
                'type' => 'note',
                'value' => $item->notes,
                'sort_order' => $last + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('items')->where('id', $item->id)->update(['urls' => $item->url]);
    }

    private function columnFor(string $type): string
    {
        return match ($type) {
            'text' => 'username',
            'password' => 'password',
            'url' => 'url',
            'totp' => 'totp_secret',
            default => throw new InvalidArgumentException("No legacy column for {$type}."),
        };
    }

    /**
     * Best effort: restores the fixed columns from the first field of each
     * kind and the password field's history. Fields added after this
     * migration stay as custom fields.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('password')->nullable()->after('username');
            $table->text('notes')->nullable()->after('password');
            $table->text('totp_secret')->nullable()->after('notes');
            $table->timestamp('password_updated_at')->nullable()->after('dedup_hash');
        });

        Schema::create('item_password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->text('password');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_id', 'created_at']);
        });

        Schema::table('item_fields', function (Blueprint $table) {
            $table->boolean('is_secret')->default(true)->after('value');
        });

        DB::table('items')->orderBy('id')->each(function (stdClass $item): void {
            $fields = DB::table('item_fields')->where('item_id', $item->id)->orderBy('sort_order')->get();
            $restore = [];

            foreach (['password' => 'password', 'totp' => 'totp_secret', 'note' => 'notes'] as $type => $column) {
                $field = $fields->firstWhere('type', $type);

                if ($field === null) {
                    continue;
                }

                $restore[$column] = $field->value;
                DB::table('item_fields')->where('id', $field->id)->delete();

                if ($type === 'password') {
                    foreach (DB::table('item_field_histories')->where('item_field_id', $field->id)->get() as $entry) {
                        DB::table('item_password_histories')->insert([
                            'item_id' => $item->id,
                            'password' => $entry->value,
                            'created_at' => $entry->created_at,
                        ]);
                    }
                }
            }

            foreach (['text' => 'username', 'url' => 'url'] as $type => $column) {
                $field = $fields->firstWhere('type', $type);

                if ($field !== null) {
                    DB::table('item_fields')->where('id', $field->id)->delete();
                }
            }

            if ($restore !== []) {
                DB::table('items')->where('id', $item->id)->update($restore);
            }
        });

        Schema::dropIfExists('item_field_histories');

        Schema::table('item_fields', function (Blueprint $table) {
            $table->dropColumn('autofill');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('urls');
        });
    }
};
