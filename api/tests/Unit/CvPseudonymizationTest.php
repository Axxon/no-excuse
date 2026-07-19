<?php

namespace Tests\Unit;

use App\Contracts\CvPseudonymizer;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\CandidatePromptBuilder;
use App\Services\HttpCvPseudonymizer;
use App\Services\LaravelAiCandidateAnalyzer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\StructuredAnonymousAgent;
use RuntimeException;
use Tests\TestCase;

class CvPseudonymizationTest extends TestCase
{
    public function test_http_service_returns_only_the_pseudonymized_text(): void
    {
        config()->set('no-excuse.pseudonymization.url', 'http://cv-pseudonymizer:8080');
        Http::fake([
            'http://cv-pseudonymizer:8080/anonymize' => Http::response([
                'pseudonymized_text' => '[CANDIDATE_NAME] maîtrise Laravel.',
                'entity_counts' => ['CANDIDATE_NAME' => 1],
                'model' => 'xx_ent_wiki_sm',
            ]),
        ]);

        $result = (new HttpCvPseudonymizer)->pseudonymize(
            'Jean Dupont maîtrise Laravel.',
            'Jean Dupont',
            'jean@example.test',
        );

        $this->assertSame('[CANDIDATE_NAME] maîtrise Laravel.', $result);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://cv-pseudonymizer:8080/anonymize'
            && $request['candidate_name'] === 'Jean Dupont'
            && $request['candidate_email'] === 'jean@example.test');
    }

    public function test_remote_analysis_fails_closed_when_pseudonymization_fails(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'failure'], 503)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucun appel IA distant n’a été effectué.');

        (new HttpCvPseudonymizer)->pseudonymize('CV privé', 'Jean Dupont', 'jean@example.test');
    }

    public function test_candidate_prompt_contains_pseudonymized_content_only(): void
    {
        $pseudonymizer = new class implements CvPseudonymizer
        {
            public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): string
            {
                return '[CANDIDATE_NAME] maîtrise Laravel et Vue.';
            }
        };
        $offer = new JobOffer([
            'title' => 'Développeur',
            'description' => 'Construire un produit.',
            'criteria' => ['Laravel', 'Vue'],
        ]);
        $application = new Application([
            'candidate_name' => 'Jean Dupont',
            'candidate_email' => 'jean@example.test',
        ]);
        $application->setRelation('offer', $offer);

        $prompt = (new CandidatePromptBuilder($pseudonymizer))->build($application, 'Jean Dupont, jean@example.test');

        $this->assertStringContainsString('[CANDIDATE_NAME]', $prompt);
        $this->assertStringNotContainsString('Jean Dupont', $prompt);
        $this->assertStringNotContainsString('jean@example.test', $prompt);
    }

    public function test_live_analyzer_stops_before_provider_when_pseudonymization_throws(): void
    {
        $pseudonymizer = new class implements CvPseudonymizer
        {
            public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): string
            {
                throw new RuntimeException('Pseudonymisation impossible.');
            }
        };
        $offer = new JobOffer([
            'title' => 'Développeur',
            'description' => 'Construire un produit.',
            'criteria' => ['Laravel'],
            'screening_provider' => 'openai',
            'screening_model' => 'gpt-test',
        ]);
        $application = new Application([
            'candidate_name' => 'Jean Dupont',
            'candidate_email' => 'jean@example.test',
        ]);
        $application->setRelation('offer', $offer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pseudonymisation impossible.');

        (new LaravelAiCandidateAnalyzer(new CandidatePromptBuilder($pseudonymizer)))
            ->screen($application, 'Jean Dupont maîtrise Laravel.');
    }

    public function test_live_analyzer_uses_structured_scores_for_every_offer_criterion(): void
    {
        StructuredAnonymousAgent::fake([[
            'score' => 84,
            'breakdown' => ['Laravel' => 90, 'Vue' => 78],
            'summary' => 'Le profil apporte des preuves professionnelles pertinentes.',
        ]])->preventStrayPrompts();

        $pseudonymizer = new class implements CvPseudonymizer
        {
            public function pseudonymize(string $text, string $candidateName, string $candidateEmail, array $professionalTerms = []): string
            {
                return '[CANDIDATE_NAME] maîtrise Laravel et Vue.';
            }
        };
        $offer = new JobOffer([
            'title' => 'Développeur',
            'description' => 'Construire un produit.',
            'criteria' => ['Laravel', 'Vue'],
            'scoring_provider' => 'openai',
            'scoring_model' => 'gpt-test',
        ]);
        $application = new Application([
            'candidate_name' => 'Jean Dupont',
            'candidate_email' => 'jean@example.test',
        ]);
        $application->setRelation('offer', $offer);

        $result = (new LaravelAiCandidateAnalyzer(new CandidatePromptBuilder($pseudonymizer)))
            ->score($application, 'Jean Dupont maîtrise Laravel et Vue.');

        $this->assertSame(84.0, $result->score);
        $this->assertSame(['Laravel' => 90.0, 'Vue' => 78.0], $result->breakdown);
    }
}
