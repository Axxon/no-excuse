<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->text('description');
            $table->json('criteria');
            $table->text('rejection_message');
            $table->text('final_rejection_message');
            $table->string('screening_provider')->default('openai');
            $table->string('screening_model')->nullable();
            $table->string('scoring_provider')->default('openai');
            $table->string('scoring_model')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->string('candidate_name');
            $table->string('candidate_email');
            $table->string('cv_path');
            $table->string('cv_original_name');
            $table->text('cover_letter')->nullable();
            $table->string('status')->default('received')->index();
            $table->decimal('scope_score', 5, 2)->nullable();
            $table->text('scope_reason')->nullable();
            $table->decimal('final_score', 5, 2)->nullable()->index();
            $table->json('score_breakdown')->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('candidate_feedback')->nullable();
            $table->unsignedSmallInteger('recruiter_rank')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('selected_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->string('status_token_hash', 64);
            $table->timestampsTz();
            $table->unique(['job_offer_id', 'candidate_email']);
        });

        Schema::create('application_annotations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestampsTz();
        });

        Schema::create('application_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('application_annotations');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_offers');
    }
};
