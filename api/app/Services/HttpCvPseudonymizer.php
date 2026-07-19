<?php

namespace App\Services;

use App\Contracts\CvPseudonymizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HttpCvPseudonymizer implements CvPseudonymizer
{
    public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): string
    {
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('no-excuse.pseudonymization.timeout_seconds'))
                ->post(rtrim((string) config('no-excuse.pseudonymization.url'), '/').'/anonymize', [
                    'text' => $text,
                    'candidate_name' => $candidateName,
                    'candidate_email' => $candidateEmail,
                    'professional_terms' => array_values($professionalTerms),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Le service local de pseudonymisation est indisponible.', previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('La pseudonymisation locale du CV a échoué.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Le service local de pseudonymisation a refusé le CV. Aucun appel IA distant n’a été effectué.');
        }

        $pseudonymized = $response->json('pseudonymized_text');
        if (! is_string($pseudonymized) || trim($pseudonymized) === '' || mb_strlen($pseudonymized) > 220000) {
            throw new RuntimeException('Le service local de pseudonymisation a retourné un résultat invalide. Aucun appel IA distant n’a été effectué.');
        }

        return $pseudonymized;
    }
}
