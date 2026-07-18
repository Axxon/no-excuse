<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'notification_sender_name' => 'Équipe recrutement',
            'notification_reply_to' => fake()->companyEmail(),
            'default_screening_provider' => 'openai',
            'default_scoring_provider' => 'anthropic',
            'screening_workers' => 1,
            'scoring_workers' => 1,
            'screening_prompt' => config('no-excuse.prompts.screening'),
            'scoring_prompt' => config('no-excuse.prompts.scoring'),
        ];
    }
}
