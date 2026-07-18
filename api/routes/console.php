<?php

use App\Models\JobOffer;
use App\Services\FinalizeOffer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (FinalizeOffer $finalize): void {
    JobOffer::query()->where('status', 'open')->where('closes_at', '<=', now())->each(fn (JobOffer $offer) => $finalize->request($offer));
    JobOffer::query()->where('status', 'closing')->each(fn (JobOffer $offer) => $finalize->request($offer));
})->name('finalize-expired-offers')->everyMinute()->withoutOverlapping();

Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('demo:notify-waitlist')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('applications:apply-retention')->daily()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily()->withoutOverlapping();
Schedule::command('notifications:reconcile')->everyFiveMinutes()->withoutOverlapping();
