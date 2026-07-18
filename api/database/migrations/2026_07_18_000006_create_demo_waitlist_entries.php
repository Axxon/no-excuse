<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->text('email');
            $table->string('email_hash', 64)->unique();
            $table->string('locale', 5)->default('fr');
            $table->string('status')->default('waiting')->index();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_waitlist_entries');
    }
};
