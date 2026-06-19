<?php

namespace App\Http\Controllers;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook requests from the profile scraper provider.
     */
    public function handleInstagram(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature');
        $requestId = $request->header('X-Webhook-Request-ID');

        if (empty($signature) || empty($requestId)) {
            return response()->json([
                'error' => 'Missing security headers. Webhooks require X-Webhook-Signature and X-Webhook-Request-ID.',
            ], 400);
        }

        // 1. HMAC Verification (SHA256)
        $secret = (string) config('services.webhook.secret', 'exhibit_social_webhook_secret_key');
        $payload = (string) $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid HMAC signature.'], 401);
        }

        // 2. Replay Protection (24-hour Cache check)
        $nonceKey = "webhook:nonce:{$requestId}";

        // set NX with EX 86400 (24h)
        $isUnique = Cache::add($nonceKey, '1', 86400);
        if (! $isUnique) {
            return response()->json([
                'error' => 'Replay attack detected. Request already processed in the last 24 hours.',
            ], 409);
        }

        // 3. Process request
        $data = json_decode($payload, true);
        $username = strtolower(trim($data['username'] ?? ''));

        if (empty($username)) {
            return response()->json(['error' => 'Username field is missing in payload.'], 422);
        }

        // Create or get the profile
        $profile = Profile::firstOrCreate([
            'username' => $username,
            'platform' => 'instagram',
        ], [
            'status' => 'pending',
        ]);

        // Dispatch background queued job
        FetchProfileJob::dispatch($profile->id);

        return response()->json([
            'status' => 'queued',
            'profile_id' => $profile->id,
            'message' => "Successfully queued FetchProfileJob for @{$username}.",
        ], 200);
    }

    /**
     * Handle incoming webhook requests from the YouTube profile scraper provider.
     */
    public function handleYoutube(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature');
        $requestId = $request->header('X-Webhook-Request-ID');

        if (empty($signature) || empty($requestId)) {
            return response()->json([
                'error' => 'Missing security headers. Webhooks require X-Webhook-Signature and X-Webhook-Request-ID.',
            ], 400);
        }

        // 1. HMAC Verification (SHA256)
        $secret = (string) config('services.webhook.secret', 'exhibit_social_webhook_secret_key');
        $payload = (string) $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid HMAC signature.'], 401);
        }

        // 2. Replay Protection (24-hour Cache check)
        $nonceKey = "webhook:nonce:{$requestId}";

        // set NX with EX 86400 (24h)
        $isUnique = Cache::add($nonceKey, '1', 86400);
        if (! $isUnique) {
            return response()->json([
                'error' => 'Replay attack detected. Request already processed in the last 24 hours.',
            ], 409);
        }

        // 3. Process request
        $data = json_decode($payload, true);
        $username = strtolower(trim($data['username'] ?? ''));

        if (empty($username)) {
            return response()->json(['error' => 'Username field is missing in payload.'], 422);
        }

        // Create or get the profile
        $profile = Profile::firstOrCreate([
            'username' => $username,
            'platform' => 'youtube',
        ], [
            'status' => 'pending',
        ]);

        // Dispatch background queued job
        FetchProfileJob::dispatch($profile->id);

        return response()->json([
            'status' => 'queued',
            'profile_id' => $profile->id,
            'message' => "Successfully queued FetchProfileJob for YouTube @{$username}.",
        ], 200);
    }
}
