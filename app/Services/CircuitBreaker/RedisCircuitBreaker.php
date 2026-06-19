<?php

namespace App\Services\CircuitBreaker;

use Illuminate\Support\Facades\Redis;

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
        $redis = Redis::connection();
        $stateKey = "cb:state:{$this->name}";
        $openUntilKey = "cb:open_until:{$this->name}";

        $state = $redis->get($stateKey) ?? 'CLOSED';

        if ($state === 'OPEN') {
            $openUntil = (float) ($redis->get($openUntilKey) ?? 0);
            if (microtime(true) > $openUntil) {
                // Cool down over: transition to HALF_OPEN to test a request
                $redis->set($stateKey, 'HALF_OPEN');

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
        $redis = Redis::connection();
        $stateKey = "cb:state:{$this->name}";
        $failuresKey = "cb:failures:{$this->name}";

        $redis->set($stateKey, 'CLOSED');
        $redis->set($failuresKey, 0);
    }

    /**
     * Record a request failure. Opens the circuit if threshold is reached.
     */
    public function recordFailure(): void
    {
        $redis = Redis::connection();
        $stateKey = "cb:state:{$this->name}";
        $failuresKey = "cb:failures:{$this->name}";
        $openUntilKey = "cb:open_until:{$this->name}";

        $failures = (int) $redis->incr($failuresKey);

        if ($failures >= $this->threshold) {
            $redis->set($stateKey, 'OPEN');
            $redis->set($openUntilKey, microtime(true) + $this->cooldown);
        }
    }

    /**
     * Force reset the circuit breaker state (useful for tests).
     */
    public function reset(): void
    {
        $redis = Redis::connection();
        $redis->del("cb:state:{$this->name}");
        $redis->del("cb:failures:{$this->name}");
        $redis->del("cb:open_until:{$this->name}");
    }
}
