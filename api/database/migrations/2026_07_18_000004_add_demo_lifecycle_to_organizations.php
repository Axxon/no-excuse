<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->index();
            $table->timestampTz('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['is_demo']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['is_demo', 'expires_at']);
        });
    }
};
