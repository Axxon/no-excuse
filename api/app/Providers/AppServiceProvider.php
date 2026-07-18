<?php

namespace App\Providers;

use App\Contracts\CandidateAnalyzer;
use App\Services\DemoCandidateAnalyzer;
use App\Services\LaravelAiCandidateAnalyzer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CandidateAnalyzer::class, fn () => config('no-excuse.ai.mode') === 'live'
            ? new LaravelAiCandidateAnalyzer
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
