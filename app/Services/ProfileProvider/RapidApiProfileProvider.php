<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RapidApiProfileProvider implements ProfileProvider
{
    /**
     * Fetch profile data by username.
     */
    public function fetchProfile(string $username): array
    {
        $apiKey = config('services.rapidapi.key');
        $apiHost = config('services.rapidapi.host', 'instagram-scraper-api2.p.rapidapi.com');

        if (empty($apiKey)) {
            throw new FatalProfileException('RapidAPI Key is not configured in .env. Falling back to FakeProfileProvider.');
        }

        try {
            $response = Http::withHeaders([
                'x-rapidapi-key' => $apiKey,
                'x-rapidapi-host' => $apiHost,
            ])
                ->timeout(15) // Explicit read timeout of 15 seconds
                ->connectTimeout(3) // Explicit connection timeout of 3 seconds
                ->get("https://{$apiHost}/v1/info", [
                    'username' => $username,
                ]);

            $status = $response->status();

            if ($response->successful()) {
                $data = $response->json();
                $userData = $data['data'] ?? $data;

                if (empty($userData['username'])) {
                    throw new FatalProfileException('Invalid response payload structure from API.');
                }

                return [
                    'username' => strtolower(trim($userData['username'])),
                    'followers_count' => (int) ($userData['follower_count'] ?? $userData['followers_count'] ?? $userData['edge_followed_by']['count'] ?? 0),
                    'following_count' => (int) ($userData['following_count'] ?? $userData['edge_follow']['count'] ?? 0),
                    'posts_count' => (int) ($userData['media_count'] ?? $userData['posts_count'] ?? $userData['edge_owner_to_timeline_media']['count'] ?? 0),
                    'bio' => $userData['biography'] ?? $userData['bio'] ?? null,
                    'profile_picture_url' => $userData['profile_pic_url'] ?? $userData['profile_picture_url'] ?? null,
                ];
            }

            if ($status === 404) {
                throw new FatalProfileException("Profile '@{$username}' not found on Instagram.");
            }

            if ($status === 401 || $status === 403) {
                throw new FatalProfileException("API key authorization error (Status {$status}).");
            }

            if ($status === 429 || ($status >= 500 && $status <= 599)) {
                throw new RetriableProfileException("Server error or rate limit hit (Status {$status}).");
            }

            throw new FatalProfileException("API returned unhandled response (Status {$status}).");
        } catch (ConnectionException $e) {
            throw new RetriableProfileException('Connection timeout or network failure: '.$e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof FatalProfileException || $e instanceof RetriableProfileException) {
                throw $e;
            }
            throw new FatalProfileException('An unexpected exception occurred: '.$e->getMessage(), 0, $e);
        }
    }
}
