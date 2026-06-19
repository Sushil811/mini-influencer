<?php

namespace App\Services\CircuitBreaker;

use Illuminate\Support\Facades\Cache;

class RedisCircuitBreaker
{
    protected string $name;

    protected int $threshold;

    protected int $cooldown;

    public function __construct(string $name = 'profile_fetch', int $threshold = 10, int $cooldown = 120)
    {
        $this->name = $name;
        $this->threshold = $threshold;
        $this->cooldown = $cooldown; // Cooldown duration in seconds
    }

    /**
     * Check if the circuit is currently open (blocking requests).
     */
    public function isOpen(): bool
    {
        $stateKey = "cb:state:{$this->name}";
        $openUntilKey = "cb:open_until:{$this->name}";

        $state = Cache::get($stateKey) ?? 'CLOSED';

        if ($state === 'OPEN') {
            $openUntil = (float) (Cache::get($openUntilKey) ?? 0);
            if (microtime(true) > $openUntil) {
                // Cool down over: transition to HALF_OPEN to test a request
                Cache::put($stateKey, 'HALF_OPEN', now()->addDays(7));

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Record a successful request. Closes the circuit.
     */
    public function recordSuccess(): void
    {
        $stateKey = "cb:state:{$this->name}";
        $failuresKey = "cb:failures:{$this->name}";

        Cache::put($stateKey, 'CLOSED', now()->addDays(7));
        Cache::put($failuresKey, 0, now()->addDays(7));
    }

    /**
     * Record a request failure. Opens the circuit if threshold is reached.
     */
    public function recordFailure(): void
    {
        $stateKey = "cb:state:{$this->name}";
        $failuresKey = "cb:failures:{$this->name}";
        $openUntilKey = "cb:open_until:{$this->name}";

        if (! Cache::has($failuresKey)) {
            Cache::put($failuresKey, 1, now()->addDays(7));
            $failures = 1;
        } else {
            $failures = (int) Cache::increment($failuresKey);
        }

        if ($failures >= $this->threshold) {
            Cache::put($stateKey, 'OPEN', now()->addDays(7));
            Cache::put($openUntilKey, microtime(true) + $this->cooldown, now()->addDays(7));
        }
    }

    /**
     * Force reset the circuit breaker state (useful for tests).
     */
    public function reset(): void
    {
        Cache::forget("cb:state:{$this->name}");
        Cache::forget("cb:failures:{$this->name}");
        Cache::forget("cb:open_until:{$this->name}");
    }
}
