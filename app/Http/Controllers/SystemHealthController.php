<?php

namespace App\Http\Controllers;

use App\Services\CircuitBreaker\RedisCircuitBreaker;
use App\Services\RateLimiter\RedisQuotaTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    /**
     * Display the system health page.
     */
    public function index(): Response
    {
        // 1. Circuit Breaker Details
        $cbState = Cache::get('cb:state:profile_fetch') ?? 'CLOSED';
        $cbFailures = (int) (Cache::get('cb:failures:profile_fetch') ?? 0);
        $cbOpenUntil = (float) (Cache::get('cb:open_until:profile_fetch') ?? 0);
        $cbCooldownRemaining = max(0, round($cbOpenUntil - microtime(true), 1));

        // 2. Token Bucket Rate Limiter Details
        $tokens = Cache::get('rl:tokens:profile_fetch');
        $tokensRemaining = $tokens !== null ? round((float) $tokens, 1) : 30.0;

        // 3. Quota details
        $quota = new RedisQuotaTracker;
        $quotaConsumed = $quota->getConsumedToday('rapidapi');

        // 4. Webhook and Replay Protection details
        $webhookSecret = (string) config('services.webhook.secret', 'exhibit_social_webhook_secret_key');

        // Check for cached nonces
        $noncesCount = 0;
        try {
            if (config('cache.default') === 'redis') {
                $noncesCount = count(Redis::connection()->keys('webhook:nonce:*'));
            } else {
                $noncesCount = DB::table('cache')->where('key', 'like', '%webhook:nonce:%')->count();
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return Inertia::render('system-health', [
            'cb' => [
                'state' => $cbState,
                'failures' => $cbFailures,
                'cooldown_remaining' => $cbCooldownRemaining,
                'threshold' => 10,
                'cooldown' => 120,
            ],
            'limiter' => [
                'tokens_remaining' => $tokensRemaining,
                'capacity' => 30,
                'refill_rate' => 0.5,
            ],
            'quota' => [
                'consumed' => $quotaConsumed,
                'limit' => 1000,
                'safety_margin' => 900,
            ],
            'webhook' => [
                'endpoint' => '/api/webhooks/instagram',
                'secret' => $webhookSecret,
                'cached_nonces' => $noncesCount,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Reset the circuit breaker state.
     */
    public function resetCircuitBreaker(): RedirectResponse
    {
        $cb = new RedisCircuitBreaker;
        $cb->reset();

        return redirect()->back()->with('success', 'Circuit breaker state has been reset to CLOSED.');
    }

    /**
     * Simulate an incoming signed webhook request.
     */
    public function simulateWebhook(Request $request): RedirectResponse
    {
        $platform = strtolower(trim($request->input('platform', 'instagram')));
        $username = strtolower(trim(ltrim(trim($request->input('username', 'simulated_user')), '@')));
        if (empty($username)) {
            return redirect()->back()->with('error', 'Please enter a valid username.');
        }

        $payload = json_encode(['username' => $username]);
        if ($payload === false) {
            $payload = '';
        }
        $secret = (string) config('services.webhook.secret', 'exhibit_social_webhook_secret_key');
        $signature = hash_hmac('sha256', $payload, $secret);
        $requestId = 'simulated-'.Str::uuid();

        try {
            // Re-create request internally to bypass webserver thread locking on Windows
            $endpoint = $platform === 'youtube' ? '/api/webhooks/youtube' : '/api/webhooks/instagram';
            $webhookRequest = Request::create(
                $endpoint,
                'POST',
                [],
                [],
                [],
                [
                    'HTTP_X-Webhook-Signature' => $signature,
                    'HTTP_X-Webhook-Request-ID' => $requestId,
                    'CONTENT_TYPE' => 'application/json',
                ],
                $payload
            );

            $webhookController = app(WebhookController::class);

            if ($platform === 'youtube') {
                $response = $webhookController->handleYoutube($webhookRequest);
            } else {
                $response = $webhookController->handleInstagram($webhookRequest);
            }

            if ($response->status() === 200) {
                $platformName = ucfirst($platform);

                return redirect()->back()->with('success', "Simulated {$platformName} webhook sent successfully! Queued FetchProfileJob for @{$username}.");
            } else {
                $data = json_decode($response->content(), true);
                $err = $data['error'] ?? 'Unknown error';

                return redirect()->back()->with('error', "Webhook simulation failed with status {$response->status()}: {$err}");
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Webhook simulation failed: '.$e->getMessage());
        }
    }
}
