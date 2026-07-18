<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('cv_path')->nullable()->change();
            $table->string('cv_original_name')->nullable()->change();
            $table->timestampTz('cv_deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn('cv_deleted_at');
            $table->string('cv_path')->nullable(false)->change();
            $table->string('cv_original_name')->nullable(false)->change();
        });
    }
};
