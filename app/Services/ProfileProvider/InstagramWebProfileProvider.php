<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class InstagramWebProfileProvider implements ProfileProvider
{
    /**
     * Fetch real profile data by username directly from Instagram's web API.
     */
    public function fetchProfile(string $username): array
    {
        $url = 'https://i.instagram.com/api/v1/users/web_profile_info/?username='.urlencode($username);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'x-ig-app-id' => '936619743392459',
                'Accept' => 'application/json',
            ])
                ->timeout(12)
                ->connectTimeout(4)
                ->get($url);

            $status = $response->status();

            if ($status === 200) {
                $data = $response->json();
                $userData = $data['data']['user'] ?? null;

                if (empty($userData) || empty($userData['username'])) {
                    throw new FatalProfileException('Invalid response payload structure from Instagram web API.');
                }

                return [
                    'username' => strtolower(trim($userData['username'])),
                    'followers_count' => (int) ($userData['edge_followed_by']['count'] ?? $userData['follower_count'] ?? 0),
                    'following_count' => (int) ($userData['edge_follow']['count'] ?? $userData['following_count'] ?? 0),
                    'posts_count' => (int) ($userData['edge_owner_to_timeline_media']['count'] ?? $userData['media_count'] ?? 0),
                    'bio' => $userData['biography'] ?? null,
                    'profile_picture_url' => $userData['profile_pic_url'] ?? null,
                ];
            }

            if ($status === 404) {
                throw new FatalProfileException("Profile '@{$username}' not found on Instagram.");
            }

            if ($status === 429) {
                throw new RetriableProfileException('Instagram rate limit hit (Status 429).');
            }

            throw new FatalProfileException("Instagram API returned error status {$status}.");
        } catch (ConnectionException $e) {
            throw new RetriableProfileException('Connection timeout to Instagram: '.$e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof FatalProfileException || $e instanceof RetriableProfileException) {
                throw $e;
            }
            throw new FatalProfileException('An unexpected exception occurred while fetching Instagram: '.$e->getMessage(), 0, $e);
        }
    }
}
