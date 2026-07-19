<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->longText('pseudonymized_cv_text')->nullable();
            $table->string('pseudonymization_version', 80)->nullable();
            $table->timestampTz('pseudonymized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['pseudonymized_cv_text', 'pseudonymization_version', 'pseudonymized_at']);
        });
    }
};
