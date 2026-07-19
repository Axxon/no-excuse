<?php

namespace App\Providers;

use App\Contracts\CandidateAnalyzer;
use App\Contracts\CvPseudonymizer;
use App\Services\DemoCandidateAnalyzer;
use App\Services\HttpCvPseudonymizer;
use App\Services\LaravelAiCandidateAnalyzer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CvPseudonymizer::class, HttpCvPseudonymizer::class);
        $this->app->bind(CandidateAnalyzer::class, fn () => config('no-excuse.ai.mode') === 'live'
            ? $this->app->make(LaravelAiCandidateAnalyzer::class)
            : new DemoCandidateAnalyzer);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
