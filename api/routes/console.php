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
    JobOffer::query()->where('status', 'open')->where('closes_at', '<=', now())->each(fn (JobOffer $offer) => $finalize->handle($offer));
})->name('finalize-expired-offers')->everyMinute()->withoutOverlapping();

Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping();
