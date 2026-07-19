<?php

namespace App\Services;

use App\Contracts\CandidateAnalyzer;
use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Models\Application;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\StructuredAnonymousAgent;
use RuntimeException;

class LaravelAiCandidateAnalyzer implements CandidateAnalyzer
{
    public function __construct(private readonly CandidatePromptBuilder $promptBuilder) {}

    public function screen(Application $application, string $cvText): ScreeningResult
    {
        $offer = $application->offer;
        $data = $this->ask(
            $this->instructions($offer->organization?->screening_prompt ?: config('no-excuse.prompts.screening'), 'Réponds uniquement en JSON avec in_scope, score et reason.'),
            $this->promptBuilder->build($application, $cvText),
            $offer->screening_provider,
            $offer->screening_model ?: config('no-excuse.ai.defaults.'.$offer->screening_provider.'.screening'),
            fn (JsonSchema $schema): array => [
                'in_scope' => $schema->boolean()->required(),
                'score' => $schema->number()->min(0)->max(100)->required(),
                'reason' => $schema->string()->min(10)->max(2000)->required(),
            ],
        );

        $inScope = Arr::get($data, 'in_scope');
        $score = Arr::get($data, 'score');
        $reason = Arr::get($data, 'reason');
        if (! is_bool($inScope) || ! is_numeric($score) || (float) $score < 0 || (float) $score > 100 || ! is_string($reason) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 2000) {
            throw new RuntimeException('Le fournisseur IA a retourné un filtrage invalide.');
        }

        return new ScreeningResult($inScope, (float) $score, trim($reason));
    }

    public function score(Application $application, string $cvText): ScoringResult
    {
        $offer = $application->offer;
        $criteria = array_values(array_unique(array_filter(array_map(
            fn (mixed $criterion): string => trim((string) $criterion),
            $offer->criteria ?? [],
        ))));
        $data = $this->ask(
            $this->instructions($offer->organization?->scoring_prompt ?: config('no-excuse.prompts.scoring'), 'Réponds uniquement selon le schéma demandé. breakdown associe exactement chaque critère annoncé à un score numérique sur 100.'),
            $this->promptBuilder->build($application, $cvText),
            $offer->scoring_provider,
            $offer->scoring_model ?: config('no-excuse.ai.defaults.'.$offer->scoring_provider.'.scoring'),
            function (JsonSchema $schema) use ($criteria): array {
                $breakdown = [];
                foreach ($criteria as $criterion) {
                    $breakdown[$criterion] = $schema->number()->min(0)->max(100)->required();
                }

                return [
                    'score' => $schema->number()->min(0)->max(100)->required(),
                    'breakdown' => $schema->object($breakdown)->withoutAdditionalProperties()->required(),
                    'summary' => $schema->string()->min(10)->max(3000)->required(),
                ];
            },
        );

        $score = Arr::get($data, 'score');
        $breakdown = Arr::get($data, 'breakdown');
        $summary = Arr::get($data, 'summary');
        if (! is_numeric($score) || (float) $score < 0 || (float) $score > 100 || ! is_array($breakdown) || $breakdown === [] || count($breakdown) > 20 || ! is_string($summary) || mb_strlen(trim($summary)) < 10 || mb_strlen($summary) > 3000) {
            throw new RuntimeException('Le fournisseur IA a retourné un scoring invalide.');
        }
        foreach ($breakdown as $criterion => $value) {
            if (! is_string($criterion) || mb_strlen($criterion) > 180 || ! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
                throw new RuntimeException('Le détail du scoring IA est invalide.');
            }
            $breakdown[$criterion] = (float) $value;
        }

        return new ScoringResult((float) $score, $breakdown, trim($summary));
    }

    /** @return array<string, mixed> */
    private function ask(string $instructions, string $prompt, string $provider, string $model, Closure $schema): array
    {
        $response = (new StructuredAnonymousAgent($instructions, [], [], $schema))->prompt(
            $prompt,
            provider: $provider,
            model: $model,
            timeout: 45,
        );
        $data = $response->toArray();

        if (! is_array($data)) {
            throw new RuntimeException('Le fournisseur IA a retourné une réponse non structurée.');
        }

        return $data;
    }

    private function instructions(string $customPrompt, string $outputFormat): string
    {
        $guardrail = (string) config('no-excuse.mandatory_ai_guardrail');

        return $guardrail."\n\nConsignes configurées par l’entreprise :\n".$customPrompt."\n\nRègles immuables prioritaires :\n".$guardrail."\n".$outputFormat;
    }
}
