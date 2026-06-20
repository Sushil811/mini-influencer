<?php

namespace App\Jobs;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;
use App\Models\Profile;
use App\Services\CircuitBreaker\RedisCircuitBreaker;
use App\Services\ProfileProvider\ProfileProviderManager;
use App\Services\RateLimiter\RedisQuotaTracker;
use App\Services\RateLimiter\RedisRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FetchProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $profileId;

    public int $attempt;

    /**
     * Create a new job instance.
     */
    public function __construct(int $profileId, int $attempt = 1)
    {
        $this->profileId = $profileId;
        $this->attempt = $attempt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobId = $this->job ? $this->job->getJobId() : (string) Str::uuid();
        $startTime = microtime(true);
        $outcome = 'success';

        $cb = new RedisCircuitBreaker;
        $limiter = new RedisRateLimiter;
        $quota = new RedisQuotaTracker;

        // 1. Circuit Breaker Check (fail-open: if cache unavailable, proceed anyway)
        try {
            if ($cb->isOpen()) {
                Log::warning((string) json_encode([
                    'job_id' => $jobId,
                    'profile_id' => $this->profileId,
                    'attempt' => $this->attempt,
                    'duration_ms' => 0,
                    'outcome' => 'circuit_breaker_open',
                ]));

                // Defer the job (retry in 2 minutes once circuit is ready to test)
                self::dispatch($this->profileId, $this->attempt)
                    ->delay(now()->addMinutes(2));

                return;
            }
        } catch (\Exception $e) {
            Log::warning('CircuitBreaker check failed, proceeding anyway: ' . $e->getMessage());
        }

        // 2. Rate Limiting + Quota Check (fail-open: if cache unavailable, skip throttling)
        try {
            // Daily YouTube/RapidAPI scraping quota ceiling: e.g., 1000 requests max
            $dailyLimit = 1000;
            $quotaAllowed = $quota->checkAndIncrement('rapidapi', $dailyLimit);

            if (! $quotaAllowed) {
                Log::error((string) json_encode([
                    'job_id' => $jobId,
                    'profile_id' => $this->profileId,
                    'attempt' => $this->attempt,
                    'duration_ms' => 0,
                    'outcome' => 'quota_limit_exceeded_warning',
                ]));

                // Re-dispatch with exponential backoff delay (don't mark as failed)
                $delay = pow(2, $this->attempt - 1);
                self::dispatch($this->profileId, $this->attempt + 1)
                    ->delay(now()->addMinutes($delay));

                return;
            }

            // Token bucket checks
            if (! $limiter->acquire('profile_fetch')) {
                Log::warning((string) json_encode([
                    'job_id' => $jobId,
                    'profile_id' => $this->profileId,
                    'attempt' => $this->attempt,
                    'duration_ms' => 0,
                    'outcome' => 'rate_limit_bucket_empty',
                ]));

                // Re-dispatch with exponential backoff delay
                $delay = pow(2, $this->attempt - 1);
                self::dispatch($this->profileId, $this->attempt + 1)
                    ->delay(now()->addMinutes($delay));

                return;
            }
        } catch (\Exception $e) {
            Log::warning('Rate limiter / quota check failed, proceeding anyway: ' . $e->getMessage());
        }

        // 3. Concurrency safety: SELECT ... FOR UPDATE SKIP LOCKED
        $profile = DB::transaction(function () {
            $query = Profile::where('id', $this->profileId);

            // Check driver for pgsql specific row locking
            if (DB::getDriverName() === 'pgsql') {
                $rawQuery = $query->getQuery();
                $rawQuery->lockForUpdate();
                /** @var mixed $dynamicQuery */
                $dynamicQuery = $rawQuery;
                $dynamicQuery->skipLocked();
            } else {
                $query->getQuery()->lockForUpdate();
            }

            $p = $query->first();

            if ($p && $p->status !== 'fetching') {
                $p->update(['status' => 'fetching']);

                return $p;
            }

            return null;
        });

        // Skip execution if row is already locked / being fetched by another worker
        if (! $profile) {
            return;
        }

        try {
            $manager = app(ProfileProviderManager::class);
            $provider = $manager->driver($profile->platform);
            $data = $provider->fetchProfile($profile->username);

            // 4. Transactional Integrity: write snapshot and update profile metrics
            DB::transaction(function () use ($profile, $data) {
                $profile->snapshots()->create([
                    'followers_count' => $data['followers_count'],
                    'following_count' => $data['following_count'],
                    'posts_count' => $data['posts_count'],
                    'created_at' => now(), // Stored in UTC
                ]);

                $profile->update([
                    'followers_count' => $data['followers_count'],
                    'following_count' => $data['following_count'],
                    'posts_count' => $data['posts_count'],
                    'bio' => $data['bio'],
                    'profile_picture_url' => $data['profile_picture_url'],
                    'status' => 'fetched',
                    'error_message' => null,
                    'last_refreshed_at' => now(),
                ]);
            });

            // Reset failures on success
            try {
                $cb->recordSuccess();
            } catch (\Exception $e) {
                Log::warning('CircuitBreaker recordSuccess failed: ' . $e->getMessage());
            }

        } catch (FatalProfileException $e) {
            $outcome = 'fatal_failure';

            $profile->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

        } catch (RetriableProfileException $e) {
            $outcome = 'retriable_failure';
            try {
                $cb->recordFailure();
            } catch (\Exception $cbEx) {
                Log::warning('CircuitBreaker recordFailure failed: ' . $cbEx->getMessage());
            }

            if ($this->attempt >= 5) {
                $profile->update([
                    'status' => 'failed',
                    'error_message' => 'Max retry attempts reached. '.$e->getMessage(),
                ]);
            } else {
                // Return to pending status for retry worker
                $profile->update([
                    'status' => 'pending',
                    'error_message' => $e->getMessage(),
                ]);

                // Exponential backoff with delay (1m, 2m, 4m, 8m, 16m...)
                $delay = pow(2, $this->attempt - 1);
                self::dispatch($this->profileId, $this->attempt + 1)
                    ->delay(now()->addMinutes($delay));
            }
        } finally {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Structured logging as JSON line
            $logPayload = [
                'job_id' => $jobId,
                'profile_id' => $this->profileId,
                'attempt' => $this->attempt,
                'duration_ms' => $durationMs,
                'outcome' => $outcome,
            ];

            if ($outcome === 'success') {
                Log::info((string) json_encode($logPayload));
            } elseif ($outcome === 'retriable_failure') {
                Log::warning((string) json_encode($logPayload));
            } else {
                Log::error((string) json_encode($logPayload));
            }
        }
    }
}
