<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();

            // Plaintext, needed for sorting/search/favicon display.
            $table->string('name')->index();
            $table->string('url', 2048)->nullable();

            // Encrypted casts — ciphertext is ~3x plaintext, so text columns.
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('notes')->nullable();
            $table->text('totp_secret')->nullable();

            $table->boolean('favorite')->default(false);
            $table->string('dedup_hash', 64);
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vault_id', 'dedup_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
