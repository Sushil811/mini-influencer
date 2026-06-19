<?php

use App\Services\CircuitBreaker\RedisCircuitBreaker;
use App\Services\RateLimiter\RedisQuotaTracker;
use App\Services\RateLimiter\RedisRateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    try {
        Redis::connection()->flushall();
    } catch (Exception $e) {
        // Ignored
    }
});

test('circuit breaker transitions correctly', function () {
    $cb = new RedisCircuitBreaker('test_cb', 3, 60);
    $cb->reset();

    // Default CLOSED
    $this->assertFalse($cb->isOpen());

    // Record failures
    $cb->recordFailure(); // 1
    $this->assertFalse($cb->isOpen());

    $cb->recordFailure(); // 2
    $this->assertFalse($cb->isOpen());

    $cb->recordFailure(); // 3 (Threshold hit)
    $this->assertTrue($cb->isOpen());

    // Record success closes the circuit
    $cb->recordSuccess();
    $this->assertFalse($cb->isOpen());
});

test('rate limiter behaves like token bucket', function () {
    $limiter = new RedisRateLimiter;
    $key = 'test_bucket';

    // Acquire multiple tokens (capacity is 5 for this test)
    for ($i = 0; $i < 5; $i++) {
        $this->assertTrue($limiter->acquire($key, 5, 0.1));
    }

    // 6th acquire should fail as bucket is empty
    $this->assertFalse($limiter->acquire($key, 5, 0.1));
});

test('quota tracker checks safety ceiling', function () {
    $tracker = new RedisQuotaTracker;
    $key = 'test_quota';

    // Set daily limit to 10
    // Within limit
    $this->assertTrue($tracker->checkAndIncrement($key, 10)); // 1
    $this->assertTrue($tracker->checkAndIncrement($key, 10)); // 2

    // Safety ceiling is 90% of 10 = 9.
    // If we set the quota count manually to 9, checkAndIncrement should refuse (return false)
    $redis = Redis::connection();
    $date = now()->timezone('Asia/Kolkata')->format('Y-m-d');
    $redis->set("quota:{$key}:{$date}", 9);

    $this->assertFalse($tracker->checkAndIncrement($key, 10));
});
