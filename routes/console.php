<?php

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    // Refresh any profile whose last_refreshed_at is older than 1 hour (or never refreshed)
    $staleProfiles = Profile::where('last_refreshed_at', '<', now()->subHour())
        ->orWhereNull('last_refreshed_at')
        ->get();

    foreach ($staleProfiles as $profile) {
        if ($profile->status !== 'fetching') {
            FetchProfileJob::dispatch($profile->id);
        }
    }
})->everyTenMinutes()->name('refresh_stale_profiles')->withoutOverlapping();
