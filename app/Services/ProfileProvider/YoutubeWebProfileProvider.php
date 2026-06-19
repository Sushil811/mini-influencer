<?php

namespace App\Services\ProfileProvider;

use App\Exceptions\FatalProfileException;
use App\Exceptions\RetriableProfileException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class YoutubeWebProfileProvider implements ProfileProvider
{
    /**
     * Fetch real YouTube channel metrics by username/handle.
     */
    public function fetchProfile(string $username): array
    {
        $handle = ltrim(trim($username), '@');
        $url = 'https://www.youtube.com/@'.urlencode($handle);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
                ->timeout(15)
                ->connectTimeout(5)
                ->get($url);

            $status = $response->status();

            if ($status === 200) {
                $body = $response->body();

                // 1. Extract Subscribers Count
                $subscribersCount = 0;
                $subscribersText = null;

                // Match 1: "subscriberCountText":{"accessibility":{"accessibilityData":{"label":"X subscribers"}}
                if (preg_match('/"subscriberCountText"\s*:\s*\{\s*"accessibility"\s*:\s*\{\s*"accessibilityData"\s*:\s*\{\s*"label"\s*:\s*"([^"]+)"/i', $body, $matches)) {
                    $subscribersText = $matches[1];
                }
                // Match 2: "subscriberCountText":{"simpleText":"X subscribers"}
                elseif (preg_match('/"subscriberCountText"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/i', $body, $matches)) {
                    $subscribersText = $matches[1];
                }
                // Match 3: Generic accessibilityData containing "subscribers" label
                elseif (preg_match('/"accessibilityData"\s*:\s*\{\s*"label"\s*:\s*"([^"]+?)\s+subscribers"/i', $body, $matches)) {
                    $subscribersText = $matches[1];
                }
                // Match 3.1: "content":"X subscribers" (Standard JSON content text)
                elseif (preg_match('/"content"\s*:\s*"([^"]+?)\s+subscribers"/i', $body, $matches)) {
                    $subscribersText = $matches[1];
                }
                // Match 3.2: "accessibilityLabel":"X subscribers" (Accessibility fallback label)
                elseif (preg_match('/"accessibilityLabel"\s*:\s*"([^"]+?)\s+subscribers"/i', $body, $matches)) {
                    $subscribersText = $matches[1];
                }
                // Match 4: Meta description fallback
                elseif (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/i', $body, $matches)) {
                    $desc = $matches[1];
                    if (preg_match('/([\d\.,KM]+)\s*subscribers/i', $desc, $subMatches)) {
                        $subscribersText = $subMatches[1];
                    }
                }

                if ($subscribersText) {
                    $subscribersCount = $this->parseAbbreviatedNumber($subscribersText);
                }

                // 2. Extract Videos Count (posts_count)
                $videosCount = 0;
                $videosText = null;

                // Match 1: "videoCountText":{"runs":[{"text":"X"}]}
                if (preg_match('/"videoCountText"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"([^"]+)"/', $body, $matches)) {
                    $videosText = $matches[1];
                }
                // Match 2: Generic accessibilityData containing "videos" label
                elseif (preg_match('/"accessibilityData"\s*:\s*\{\s*"label"\s*:\s*"([^"]+?)\s+videos"/i', $body, $matches)) {
                    $videosText = $matches[1];
                }
                // Match 3: Meta description fallback
                elseif (isset($desc) && preg_match('/([\d\.,KM]+)\s*videos/i', $desc, $vidMatches)) {
                    $videosText = $vidMatches[1];
                }
                // Match 4: Raw text search
                elseif (preg_match('/([\d\.,KM]+)\s*videos/i', $body, $matches)) {
                    $videosText = $matches[1];
                }

                if ($videosText) {
                    $videosCount = $this->parseAbbreviatedNumber($videosText);
                }

                // 3. Profile picture URL
                $avatarUrl = null;
                if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $body, $matches)) {
                    $avatarUrl = $matches[1];
                } elseif (preg_match('/<link\s+rel="image_src"\s+href="([^"]+)"/i', $body, $matches)) {
                    $avatarUrl = $matches[1];
                }

                // 4. Bio / Description
                $bio = null;
                if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $body, $matches)) {
                    $bio = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                } elseif (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/i', $body, $matches)) {
                    $bio = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                }

                // If we couldn't parse anything at all, throw exception
                if ($subscribersCount === 0 && $videosCount === 0 && empty($bio)) {
                    throw new FatalProfileException('Failed to parse YouTube channel metrics from page payload.');
                }

                return [
                    'username' => strtolower(trim($handle)),
                    'followers_count' => $subscribersCount,
                    'following_count' => 0,
                    'posts_count' => $videosCount,
                    'bio' => $bio ? trim($bio) : null,
                    'profile_picture_url' => $avatarUrl ? trim($avatarUrl) : null,
                ];
            }

            if ($status === 404) {
                throw new FatalProfileException("YouTube channel '@{$handle}' not found.");
            }

            if ($status === 429) {
                throw new RetriableProfileException('YouTube rate limit hit (Status 429).');
            }

            throw new FatalProfileException("YouTube API returned error status {$status}.");
        } catch (ConnectionException $e) {
            throw new RetriableProfileException('Connection timeout to YouTube: '.$e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof FatalProfileException || $e instanceof RetriableProfileException) {
                throw $e;
            }
            throw new FatalProfileException('An unexpected exception occurred while fetching YouTube: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert abbreviated numbers (e.g. 1.2M, 4.5K) to integers.
     */
    private function parseAbbreviatedNumber(string $str): int
    {
        $str = strtolower(trim($str));
        // Strip out non-numeric and non-multiplier characters
        $str = str_replace([',', ' ', 'subscribers', 'subscriber', 'videos', 'video'], '', $str);

        if (str_ends_with($str, 'm') || str_contains($str, 'million')) {
            $num = str_replace('million', '', $str);

            return (int) (floatval($num) * 1000000);
        }
        if (str_ends_with($str, 'k') || str_contains($str, 'thousand')) {
            $num = str_replace('thousand', '', $str);

            return (int) (floatval($num) * 1000);
        }
        if (str_ends_with($str, 'b') || str_contains($str, 'billion')) {
            $num = str_replace('billion', '', $str);

            return (int) (floatval($num) * 1000000000);
        }

        return (int) floatval($str);
    }
}
