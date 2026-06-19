<?php

namespace App\Services\RateLimiter;

use Illuminate\Support\Facades\Cache;

class RedisQuotaTracker
{
    /**
     * Check if we are within the quota limit and increment if allowed.
     *
     * @param  string  $key  Name of the service
     * @param  int  $dailyLimit  Daily request ceiling
     * @return bool True if allowed (and incremented), false if ceiling / 10% safety margin hit
     */
    public function checkAndIncrement(string $key = 'rapidapi', int $dailyLimit = 1000): bool
    {
        // Use IST date
        $date = now()->timezone('Asia/Kolkata')->format('Y-m-d');
        $quotaKey = "quota:{$key}:{$date}";

        $current = (int) (Cache::get($quotaKey) ?? 0);

        // Refuse to dispatch when within 10% of the ceiling
        $ceilingSafetyMargin = (int) ($dailyLimit * 0.9);
        if ($current >= $ceilingSafetyMargin) {
            return false;
        }

        // Increment and set expiry (48h) to clean up keys automatically
        if (! Cache::has($quotaKey)) {
            Cache::put($quotaKey, 1, now()->addDays(2));
        } else {
            Cache::increment($quotaKey);
        }

        return true;
    }

    /**
     * Get the quota units consumed today in IST.
     */
    public function getConsumedToday(string $key = 'rapidapi'): int
    {
        $date = now()->timezone('Asia/Kolkata')->format('Y-m-d');

        return (int) (Cache::get("quota:{$key}:{$date}") ?? 0);
    }
}
