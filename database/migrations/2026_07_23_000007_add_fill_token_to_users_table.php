<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Separate from device_token: this one is embedded in a web page by
            // the in-page autofill filler (higher exposure), so it is rotated
            // independently and is Origin-scoped in LookupController.
            $table->string('fill_token', 64)->nullable()->unique()->after('device_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fill_token');
        });
    }
};
