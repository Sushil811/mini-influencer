<?php

namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Redis;

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
        $redis = Redis::connection();
        $now = microtime(true);

        $lockKey = "rl:lock:{$key}";
        $tokensKey = "rl:tokens:{$key}";
        $lastRefillKey = "rl:refill:{$key}";

        // Atomic lock to update bucket state
        /** @var mixed $redisClient */
        $redisClient = $redis;
        $lock = $redisClient->set($lockKey, '1', 'EX', 2, 'NX');
        if (! $lock) {
            usleep(25000); // 25ms delay retry
            $lock = $redisClient->set($lockKey, '1', 'EX', 2, 'NX');
            if (! $lock) {
                return false;
            }
        }

        try {
            $tokens = $redis->get($tokensKey);
            $lastRefill = $redis->get($lastRefillKey);

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
                $redis->set($tokensKey, $tokens);
                $redis->set($lastRefillKey, $lastRefill);

                return true;
            }

            // Save state even on failure (preserves timestamp updates)
            $redis->set($tokensKey, $tokens);
            $redis->set($lastRefillKey, $lastRefill);

            return false;

        } finally {
            $redis->del($lockKey);
        }
    }
}
