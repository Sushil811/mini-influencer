<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;

class ProfileProviderManager
{
    /**
     * Resolve the profile provider for the specified platform.
     */
    public function driver(string $platform): ProfileProvider
    {
        // If a test has registered a mock instance of ProfileProvider, return it directly.
        $instances = \Closure::bind(fn ($c) => $c->instances, null, app())(app());
        if (isset($instances[ProfileProvider::class])) {
            return $instances[ProfileProvider::class];
        }

        $platform = strtolower(trim($platform));

        if ($platform === 'instagram') {
            if (config('services.scraper.mock') || app()->environment('testing')) {
                return new FakeProfileProvider;
            }
            if (! empty(config('services.rapidapi.key'))) {
                return new RapidApiProfileProvider;
            }

            return new InstagramWebProfileProvider;
        }

        if ($platform === 'youtube') {
            if (config('services.scraper.mock') || app()->environment('testing')) {
                return new YoutubeFakeProfileProvider;
            }

            return new YoutubeWebProfileProvider;
        }

        throw new FatalProfileException("Unsupported platform: {$platform}");
    }
}
