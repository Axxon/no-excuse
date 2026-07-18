<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rejected_cv_retention_days')->default(0);
            $table->unsignedSmallInteger('selected_cv_retention_days')->default(90);
            $table->unsignedSmallInteger('candidate_data_retention_days')->default(730);
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestampTz('personal_data_deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('personal_data_deleted_at'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn([
            'rejected_cv_retention_days', 'selected_cv_retention_days', 'candidate_data_retention_days',
        ]));
    }
};
