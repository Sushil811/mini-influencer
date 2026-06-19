<?php

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\ProfileProvider\ProfileProvider;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    try {
        Redis::connection()->flushall();
    } catch (Exception $e) {
        // Ignored
    }
});

test('job skips execution if profile status is already fetching', function () {
    // Create a mock provider to assert it is never called
    $mockProvider = Mockery::mock(ProfileProvider::class);
    $mockProvider->shouldNotReceive('fetchProfile');
    $this->app->instance(ProfileProvider::class, $mockProvider);

    // Create a profile whose status is 'fetching' (simulating another worker currently processing it)
    $profile = Profile::factory()->create([
        'username' => 'concurrency_test',
        'status' => 'fetching',
    ]);

    // Dispatch the job
    $job = new FetchProfileJob($profile->id);
    $job->handle();

    // Verify status remains 'fetching' and no provider calls occurred
    $this->assertEquals('fetching', $profile->fresh()->status);
});

test('job executes successfully if profile status is pending', function () {
    // Mock provider to return mock data once
    $mockProvider = Mockery::mock(ProfileProvider::class);
    $mockProvider->shouldReceive('fetchProfile')
        ->once()
        ->with('concurrency_test')
        ->andReturn([
            'followers_count' => 5000,
            'following_count' => 300,
            'posts_count' => 150,
            'bio' => 'Test Bio',
            'profile_picture_url' => 'http://example.com/pic.jpg',
        ]);
    $this->app->instance(ProfileProvider::class, $mockProvider);

    $profile = Profile::factory()->create([
        'username' => 'concurrency_test',
        'status' => 'pending',
    ]);

    $job = new FetchProfileJob($profile->id);
    $job->handle();

    $this->assertEquals('fetched', $profile->fresh()->status);
    $this->assertEquals(5000, $profile->fresh()->followers_count);
});
