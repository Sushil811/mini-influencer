<?php

namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Cache;

class RedisRateLimiter
{
    /**
     * Attempt to acquire a token from the bucket.
     *
     * @param  string  $key  Name of the bucket
     * @param  int  $capacity  Maximum tokens the bucket can hold
     * @param  float  $refillRate  Tokens refilled per second (e.g. 0.5 = 1 token/2s)
     * @return bool True if a token was consumed, false if bucket is empty
     */
    public function acquire(string $key = 'profile_fetch', int $capacity = 30, float $refillRate = 0.5): bool
    {
        $now = microtime(true);

        $lockKey = "rl:lock:{$key}";
        $tokensKey = "rl:tokens:{$key}";
        $lastRefillKey = "rl:refill:{$key}";

        // Use Laravel Cache atomic locks (works on Redis, DB, Memcached, etc.)
        $lock = Cache::lock($lockKey, 2);

        $acquired = $lock->get();
        if (! $acquired) {
            usleep(25000); // 25ms delay retry
            $acquired = $lock->get();
            if (! $acquired) {
                return false;
            }
        }

        try {
            $tokens = Cache::get($tokensKey);
            $lastRefill = Cache::get($lastRefillKey);

            if ($tokens === null) {
                // Initialize
                $tokens = (float) $capacity;
                $lastRefill = $now;
            } else {
                $tokens = (float) $tokens;
                $lastRefill = (float) $lastRefill;

                // Refill: elapsed seconds * refillRate
                $elapsed = max(0, $now - $lastRefill);
                $refilled = $elapsed * $refillRate;
                $tokens = min((float) $capacity, $tokens + $refilled);
                $lastRefill = $now;
            }

            if ($tokens >= 1.0) {
                $tokens -= 1.0;
                Cache::put($tokensKey, $tokens, now()->addDays(7));
                Cache::put($lastRefillKey, $lastRefill, now()->addDays(7));

                return true;
            }

            // Save state even on failure (preserves timestamp updates)
            Cache::put($tokensKey, $tokens, now()->addDays(7));
            Cache::put($lastRefillKey, $lastRefill, now()->addDays(7));

            return false;

        } finally {
            $lock->release();
        }
    }
}
