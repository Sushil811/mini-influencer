<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use App\Services\RateLimiter\RedisQuotaTracker;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard page with statistics.
     */
    public function index(): Response
    {
        // DB stats
        $totalProfiles = Profile::count();
        $pendingScrapes = Profile::where('status', 'pending')->count();
        $activeScrapes = Profile::where('status', 'fetching')->count();
        $fetchedScrapes = Profile::where('status', 'fetched')->count();
        $failedScrapes = Profile::where('status', 'failed')->count();
        $totalSnapshots = ProfileSnapshot::count();

        // Top 5 influencers by followers count
        $topInfluencers = Profile::where('status', 'fetched')
            ->orderBy('followers_count', 'desc')
            ->limit(5)
            ->get();

        // Recent 5 snapshots
        $recentSnapshots = ProfileSnapshot::with('profile')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($snapshot) {
                return [
                    'id' => $snapshot->id,
                    'username' => $snapshot->profile->username ?? 'Unknown',
                    'followers_count' => $snapshot->followers_count,
                    'created_at' => $snapshot->created_at->toIso8601String(),
                ];
            });

        // Redis System States
        $cbState = Redis::connection()->get('cb:state:profile_fetch') ?? 'CLOSED';

        $tokens = Redis::connection()->get('rl:tokens:profile_fetch');
        $tokensRemaining = $tokens !== null ? round((float) $tokens, 1) : 30.0;

        $quota = new RedisQuotaTracker;
        $quotaConsumed = $quota->getConsumedToday('rapidapi');
        $quotaLimit = 1000;

        return Inertia::render('dashboard', [
            'stats' => [
                'total_profiles' => $totalProfiles,
                'pending_scrapes' => $pendingScrapes,
                'active_scrapes' => $activeScrapes,
                'fetched_scrapes' => $fetchedScrapes,
                'failed_scrapes' => $failedScrapes,
                'total_snapshots' => $totalSnapshots,
            ],
            'system' => [
                'circuit_breaker_state' => $cbState,
                'rate_limiter_tokens' => $tokensRemaining,
                'quota_consumed' => $quotaConsumed,
                'quota_limit' => $quotaLimit,
            ],
            'top_influencers' => $topInfluencers,
            'recent_snapshots' => $recentSnapshots,
        ]);
    }
}
