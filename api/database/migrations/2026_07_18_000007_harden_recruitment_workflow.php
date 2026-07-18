<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table): void {
            $table->timestampTz('closure_requested_at')->nullable()->index();
            $table->timestampTz('finalized_at')->nullable();
            $table->foreignId('selected_application_id')->nullable()->unique()->constrained('applications')->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->string('processing_stage')->nullable()->index();
            $table->text('processing_error')->nullable();
            $table->foreignId('screening_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('screening_reviewed_at')->nullable();
            $table->string('notification_state')->default('none')->index();
            $table->text('notification_error')->nullable();
            $table->timestampTz('notification_attempted_at')->nullable();
            $table->uuid('notification_message_id')->nullable()->unique();
        });

        Schema::table('application_events', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('screening_reviewed_by');
            $table->dropColumn([
                'processing_stage', 'processing_error', 'screening_reviewed_at', 'notification_state',
                'notification_error', 'notification_attempted_at', 'notification_message_id',
            ]);
        });
        Schema::table('job_offers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_application_id');
            $table->dropColumn(['closure_requested_at', 'finalized_at']);
        });
    }
};
