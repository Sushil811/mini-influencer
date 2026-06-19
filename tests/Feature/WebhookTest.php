use App\Jobs\FetchProfileJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    try {
        Cache::flush();
        Redis::connection()->flushall();
    } catch (Exception $e) {
        // Redis not running or configured differently
    }
});

test('webhook fails with 400 if missing headers', function () {
    $this->postJson('/api/webhooks/instagram', ['username' => 'test_user'])
        ->assertStatus(400)
        ->assertJsonStructure(['error']);
});

test('webhook fails with 401 if signature is invalid', function () {
    $this->withHeaders([
        'X-Webhook-Signature' => 'invalid_signature',
        'X-Webhook-Request-ID' => 'test-request-id-1',
    ])->postJson('/api/webhooks/instagram', ['username' => 'test_user'])
        ->assertStatus(401)
        ->assertJsonPath('error', 'Invalid HMAC signature.');
});

test('webhook succeeds and dispatches job with valid signature', function () {
    Queue::fake();

    $payload = ['username' => 'test_user'];
    $secret = env('WEBHOOK_SECRET', 'exhibit_social_webhook_secret_key');
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $this->withHeaders([
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Request-ID' => 'test-request-id-success',
    ])->postJson('/api/webhooks/instagram', $payload)
        ->assertStatus(200)
        ->assertJsonPath('status', 'queued');

    $this->assertDatabaseHas('profiles', [
        'username' => 'test_user',
    ]);

    Queue::assertPushed(FetchProfileJob::class);
});

test('webhook blocks replay attacks', function () {
    Queue::fake();

    $payload = ['username' => 'test_user2'];
    $secret = env('WEBHOOK_SECRET', 'exhibit_social_webhook_secret_key');
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    // First request - succeeds
    $this->withHeaders([
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Request-ID' => 'duplicate-request-id',
    ])->postJson('/api/webhooks/instagram', $payload)
        ->assertStatus(200);

    // Second request with same ID - blocked (409 Conflict)
    $this->withHeaders([
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Request-ID' => 'duplicate-request-id',
    ])->postJson('/api/webhooks/instagram', $payload)
        ->assertStatus(409)
        ->assertJsonPath('error', 'Replay attack detected. Request already processed in the last 24 hours.');
});

test('youtube webhook succeeds and dispatches job with valid signature', function () {
    Queue::fake();

    $payload = ['username' => 'test_youtube_user'];
    $secret = env('WEBHOOK_SECRET', 'exhibit_social_webhook_secret_key');
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $this->withHeaders([
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Request-ID' => 'test-youtube-request-id-success',
    ])->postJson('/api/webhooks/youtube', $payload)
        ->assertStatus(200)
        ->assertJsonPath('status', 'queued');

    $this->assertDatabaseHas('profiles', [
        'username' => 'test_youtube_user',
        'platform' => 'youtube',
    ]);

    Queue::assertPushed(FetchProfileJob::class);
});
