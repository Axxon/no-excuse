<?php

namespace App\Services;

use App\Contracts\CandidateAnalyzer;
use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Models\JobOffer;
use Illuminate\Support\Arr;
use Laravel\Ai\AnonymousAgent;
use RuntimeException;

class LaravelAiCandidateAnalyzer implements CandidateAnalyzer
{
    public function screen(JobOffer $offer, string $cvText): ScreeningResult
    {
        $data = $this->ask(
            ($offer->organization?->screening_prompt ?: config('no-excuse.prompts.screening')).' Réponds uniquement en JSON avec in_scope, score et reason.',
            $this->prompt($offer, $cvText),
            $offer->screening_provider,
            $offer->screening_model ?: config('no-excuse.ai.defaults.'.$offer->screening_provider.'.screening'),
        );

        return new ScreeningResult((bool) Arr::get($data, 'in_scope'), (float) Arr::get($data, 'score'), (string) Arr::get($data, 'reason'));
    }

    public function score(JobOffer $offer, string $cvText): ScoringResult
    {
        $data = $this->ask(
            ($offer->organization?->scoring_prompt ?: config('no-excuse.prompts.scoring')).' Réponds uniquement en JSON avec score, breakdown et summary. breakdown associe chaque critère à un score sur 100.',
            $this->prompt($offer, $cvText),
            $offer->scoring_provider,
            $offer->scoring_model ?: config('no-excuse.ai.defaults.'.$offer->scoring_provider.'.scoring'),
        );

        return new ScoringResult((float) Arr::get($data, 'score'), (array) Arr::get($data, 'breakdown', []), (string) Arr::get($data, 'summary'));
    }

    /** @return array<string, mixed> */
    private function ask(string $instructions, string $prompt, string $provider, string $model): array
    {
        $response = (new AnonymousAgent($instructions, [], []))->prompt(
            $prompt,
            provider: $provider,
            model: $model,
            timeout: 45,
        );
        $json = trim($response->text, " \n\r\t`");
        $json = preg_replace('/^json\s*/i', '', $json) ?? $json;
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('Le fournisseur IA a retourné une réponse non structurée.');
        }

        return $data;
    }

    private function prompt(JobOffer $offer, string $cvText): string
    {
        return json_encode([
            'offer' => ['title' => $offer->title, 'description' => $offer->description, 'criteria' => $offer->criteria],
            'cv_text' => mb_substr($cvText, 0, 24000),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
