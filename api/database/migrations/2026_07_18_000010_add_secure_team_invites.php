<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->timestampTz('invitation_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['invitation_token_hash', 'invitation_expires_at']));
    }
};
