<?php

namespace Database\Seeders;

use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'demo@no-excuse.test'], [
            'name' => 'Camille Martin',
            'password' => 'demo-password-2026',
        ]);

        JobOffer::firstOrCreate(['user_id' => $user->id, 'title' => 'Développeur·se Laravel / Vue'], [
            'company' => 'No Excuse Studio',
            'location' => 'Paris ou télétravail',
            'description' => 'Nous recherchons une personne capable de construire et maintenir un produit SaaS moderne avec Laravel, Vue, TypeScript, PostgreSQL et des traitements asynchrones.',
            'criteria' => ['Laravel', 'Vue', 'TypeScript', 'PostgreSQL', 'Redis', 'tests automatisés'],
            'rejection_message' => "Merci pour votre candidature. Après analyse, l'expérience présentée ne correspond pas suffisamment au périmètre de cette offre.",
            'final_rejection_message' => 'Merci pour la qualité de votre candidature. Nous avons retenu un autre profil pour cette campagne, mais souhaitions vous transmettre une décision explicite.',
            'screening_provider' => 'openai',
            'screening_model' => null,
            'scoring_provider' => 'anthropic',
            'scoring_model' => null,
            'ingestion_key_hash' => hash('sha256', 'demo-intake-key-change-me'),
            'status' => 'open',
            'opens_at' => now(),
            'closes_at' => now()->addDays(14),
        ]);
    }
}
