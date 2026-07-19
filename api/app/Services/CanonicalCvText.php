<?php

namespace App\Services;

use App\Contracts\CvPseudonymizer;
use App\Models\Application;
use RuntimeException;

class CanonicalCvText
{
    public function __construct(
        private readonly CvTextExtractor $extractor,
        private readonly CvPseudonymizer $pseudonymizer,
    ) {}

    public function for(Application $application): string
    {
        $application->refresh()->loadMissing('offer');
        if (filled($application->pseudonymized_cv_text)) {
            return $application->pseudonymized_cv_text;
        }
        if (! filled($application->cv_path)) {
            throw new RuntimeException('Le CV original est indisponible pour sa pseudonymisation locale. Aucun appel IA distant n’a été effectué.');
        }

        $result = $this->pseudonymizer->pseudonymize(
            $this->extractor->extract($application->cv_path),
            $application->candidate_name,
            $application->candidate_email,
            array_values(array_filter([
                $application->offer->title,
                ...($application->offer->criteria ?? []),
            ], 'is_string')),
        );

        $application->update([
            'pseudonymized_cv_text' => $result->text,
            'pseudonymization_version' => $result->version,
            'pseudonymized_at' => now(),
        ]);
        $application->events()->create([
            'type' => 'cv_pseudonymized',
            'metadata' => ['version' => $result->version],
        ]);

        return $result->text;
    }
}
