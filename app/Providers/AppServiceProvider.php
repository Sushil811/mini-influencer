<?php

namespace App\Providers;

use App\Services\ProfileProvider\ProfileProvider;
use App\Services\ProfileProvider\ProfileProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileProviderManager::class, function ($app) {
            return new ProfileProviderManager;
        });

        $this->app->bind(ProfileProvider::class, function ($app) {
            return $app->make(ProfileProviderManager::class)->driver('instagram');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Listen for completed or failing jobs to update health check timestamps
        Queue::after(function (JobProcessed $event) {
            try {
                Redis::connection()->set('queue:last_processed_at', time());
            } catch (\Exception $e) {
                // Ignore if redis is unavailable
            }
        });

        Queue::failing(function (JobFailed $event) {
            try {
                Redis::connection()->set('queue:last_processed_at', time());
            } catch (\Exception $e) {
                // Ignore
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
