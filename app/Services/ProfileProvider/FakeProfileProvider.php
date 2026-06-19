<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;

class FakeProfileProvider implements ProfileProvider
{
    /**
     * Fetch profile data by username.
     */
    public function fetchProfile(string $username): array
    {
        $normalized = strtolower(trim($username));

        // Simulated error triggers for testing
        if (str_contains($normalized, 'notfound')) {
            throw new FatalProfileException("Profile '@{$username}' not found on Instagram (simulated).");
        }

        if (str_contains($normalized, 'unauthorized')) {
            throw new FatalProfileException('API key authorization error (simulated).');
        }

        if (str_contains($normalized, 'timeout') || str_contains($normalized, 'rate_limit')) {
            throw new RetriableProfileException('Server error or rate limit hit (simulated).');
        }

        // Return structured mock data with small random adjustments
        // so that subsequent refreshes show realistic deltas (+/-)
        $baseFollowers = (strlen($normalized) * 23500) + 5000;
        $randomDelta = rand(-500, 1500);

        return [
            'username' => $normalized,
            'followers_count' => max(100, $baseFollowers + $randomDelta),
            'following_count' => max(10, (strlen($normalized) * 110) + rand(-10, 15)),
            'posts_count' => max(5, (strlen($normalized) * 12) + rand(-1, 3)),
            'bio' => "This is a simulated bio for @{$normalized}. Built for Exhibit Group Full-Stack Developer Assessment.",
            'profile_picture_url' => "https://picsum.photos/seed/{$normalized}/150/150",
        ];
    }
}
