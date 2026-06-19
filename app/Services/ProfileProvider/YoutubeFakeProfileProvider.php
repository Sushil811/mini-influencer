<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;

class YoutubeFakeProfileProvider implements ProfileProvider
{
    /**
     * Fetch profile data by username.
     */
    public function fetchProfile(string $username): array
    {
        $normalized = strtolower(trim($username));

        // Simulated error triggers for testing
        if (str_contains($normalized, 'notfound')) {
            throw new FatalProfileException("Channel '@{$username}' not found on YouTube (simulated).");
        }

        if (str_contains($normalized, 'unauthorized')) {
            throw new FatalProfileException('YouTube API key authorization error (simulated).');
        }

        if (str_contains($normalized, 'timeout') || str_contains($normalized, 'rate_limit')) {
            throw new RetriableProfileException('YouTube API rate limit hit (simulated).');
        }

        // Return structured mock data with small random adjustments
        // so that subsequent refreshes show realistic deltas (+/-)
        $baseSubscribers = (strlen($normalized) * 45000) + 15000;
        $randomDelta = rand(-1000, 5000);

        return [
            'username' => $normalized,
            'followers_count' => max(100, $baseSubscribers + $randomDelta),
            'following_count' => 0,
            'posts_count' => max(10, (strlen($normalized) * 24) + rand(-2, 5)),
            'bio' => "This is a simulated YouTube bio for @{$normalized}. Delivering high-quality content and video analysis.",
            'profile_picture_url' => "https://picsum.photos/seed/yt-{$normalized}/150/150",
        ];
    }
}
