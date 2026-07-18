<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_waitlist_entries', function (Blueprint $table): void {
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->timestampTz('reserved_until')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('demo_waitlist_entries', function (Blueprint $table): void {
            $table->dropColumn(['access_token_hash', 'reserved_until']);
        });
    }
};
