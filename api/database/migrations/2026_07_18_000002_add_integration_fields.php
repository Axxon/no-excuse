<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table): void {
            $table->string('ingestion_key_hash', 64)->nullable()->after('scoring_model');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->string('source', 80)->default('manual')->after('job_offer_id');
            $table->string('external_reference', 190)->nullable()->after('source');
            $table->unique(['job_offer_id', 'source', 'external_reference'], 'applications_source_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropUnique('applications_source_reference_unique');
            $table->dropColumn(['source', 'external_reference']);
        });

        Schema::table('job_offers', function (Blueprint $table): void {
            $table->dropColumn('ingestion_key_hash');
        });
    }
};
