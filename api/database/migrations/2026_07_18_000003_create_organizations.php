<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('notification_sender_name')->nullable();
            $table->string('notification_reply_to')->nullable();
            $table->string('default_screening_provider')->default('openai');
            $table->string('default_screening_model')->nullable();
            $table->string('default_scoring_provider')->default('anthropic');
            $table->string('default_scoring_model')->nullable();
            $table->unsignedTinyInteger('screening_workers')->default(1);
            $table->unsignedTinyInteger('scoring_workers')->default(1);
            $table->text('screening_prompt')->nullable();
            $table->text('scoring_prompt')->nullable();
            $table->timestampsTz();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('recruiter')->after('organization_id');
        });

        Schema::table('job_offers', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $organizationId = DB::table('organizations')->insertGetId([
                'public_id' => (string) Str::uuid7(),
                'name' => 'Entreprise de '.$user->name,
                'notification_sender_name' => $user->name,
                'notification_reply_to' => $user->email,
                'default_screening_provider' => 'openai',
                'default_scoring_provider' => 'anthropic',
                'screening_workers' => 1,
                'scoring_workers' => 1,
                'screening_prompt' => config('no-excuse.prompts.screening'),
                'scoring_prompt' => config('no-excuse.prompts.scoring'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $user->id)->update(['organization_id' => $organizationId, 'role' => 'owner']);
            DB::table('job_offers')->where('user_id', $user->id)->update(['organization_id' => $organizationId]);
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('organizations');
    }
};
