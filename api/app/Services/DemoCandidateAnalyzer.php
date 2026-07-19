<?php

namespace App\Services;

use App\Contracts\CandidateAnalyzer;
use App\Data\ScoringResult;
use App\Data\ScreeningResult;
use App\Models\Application;
use Illuminate\Support\Str;

class DemoCandidateAnalyzer implements CandidateAnalyzer
{
    public function screen(Application $application, string $cvText): ScreeningResult
    {
        $offer = $application->offer;
        $keywords = $this->keywords(implode(' ', $offer->criteria));
        $candidate = $this->keywords($cvText);
        $matches = array_values(array_intersect($keywords, $candidate));
        $missing = array_values(array_diff($this->keywords(implode(' ', $offer->criteria)), $candidate));
        $score = min(100, round((count($matches) / max(3, count($keywords))) * 180, 2));

        return new ScreeningResult(
            $score >= config('no-excuse.scope_threshold'),
            $score,
            $matches === []
                ? 'Le CV ne présente pas d’élément démontrant les compétences principales attendues pour cette offre : '.implode(', ', array_slice($missing, 0, 6)).'.'
                : 'Le CV mentionne '.implode(', ', array_slice($matches, 0, 5)).', mais ne démontre pas suffisamment les critères principaux suivants : '.implode(', ', array_slice($missing, 0, 6)).'.',
        );
    }

    public function score(Application $application, string $cvText): ScoringResult
    {
        $screening = $this->screen($application, $cvText);
        $content = Str::lower(Str::ascii($cvText));
        $experience = min(100, preg_match_all('/\b(20\d{2}|19\d{2})\b/', $content) * 12 + 35);
        $clarity = min(100, 45 + intdiv(strlen($content), 120));
        $skills = $screening->score;
        $score = round(($skills * 0.6) + ($experience * 0.25) + ($clarity * 0.15), 2);

        return new ScoringResult($score, [
            'adéquation' => $skills,
            'expérience' => (float) $experience,
            'clarté' => (float) $clarity,
        ], "Adéquation lexicale démontrable avec l'offre. {$screening->reason}");
    }

    /** @return list<string> */
    private function keywords(string $text): array
    {
        $stopWords = ['avec', 'dans', 'pour', 'vous', 'nous', 'une', 'des', 'les', 'the', 'and', 'that', 'sur', 'est', 'sont', 'plus'];
        $words = preg_split('/[^a-z0-9+#.]+/', Str::lower(Str::ascii($text))) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) >= 3 && ! in_array($word, $stopWords, true))));
    }
}
