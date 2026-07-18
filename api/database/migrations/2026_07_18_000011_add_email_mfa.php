<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('mfa_email_enabled')->default(false);
            $table->string('mfa_code_hash')->nullable();
            $table->timestampTz('mfa_code_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['mfa_email_enabled', 'mfa_code_hash', 'mfa_code_expires_at']));
    }
};
