<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController as WatchlistController;
use App\Http\Controllers\SystemHealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    $dbOk = false;
    $dbError = null;
    try {
        DB::connection()->getPdo();
        $dbOk = true;
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }

    $redisOk = false;
    $redisError = null;
    try {
        Redis::connection()->ping();
        $redisOk = true;
    } catch (Exception $e) {
        $redisError = $e->getMessage();
    }

    $queueStatus = 'unknown';
    $queueOk = true;
    $lastProcessed = null;

    if ($redisOk) {
        try {
            $lastProcessed = Redis::connection()->get('queue:last_processed_at');
            if ($lastProcessed) {
                $diff = time() - (int) $lastProcessed;
                if ($diff <= 300) {
                    $queueStatus = 'active';
                } else {
                    $queueStatus = 'stale';
                    $queueOk = false;
                }
            } else {
                $queueStatus = 'no_jobs_processed_yet';
            }
        } catch (Exception $e) {
            $queueStatus = 'error';
            $queueOk = false;
        }
    } else {
        $queueStatus = 'redis_unavailable';
        $queueOk = false;
    }

    $allOk = $dbOk && $redisOk && $queueOk;

    return response()->json([
        'status' => $allOk ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => [
                'status' => $dbOk ? 'up' : 'down',
                'error' => $dbError,
            ],
            'redis' => [
                'status' => $redisOk ? 'up' : 'down',
                'error' => $redisError,
            ],
            'queue' => [
                'status' => $queueStatus,
                'last_processed_at' => $lastProcessed ? date('Y-m-d H:i:s', (int) $lastProcessed) : null,
            ],
        ],
    ], $allOk ? 200 : 503);
});

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Watchlist Routes
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/watchlist/{profile}', [WatchlistController::class, 'show'])->name('watchlist.show');
    Route::post('/watchlist/{profile}/refetch', [WatchlistController::class, 'refetch'])->name('watchlist.refetch');
    Route::delete('/watchlist/{profile}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    // System Health & Monitoring Routes
    Route::get('/system-health', [SystemHealthController::class, 'index'])->name('system-health.index');
    Route::post('/system-health/reset-cb', [SystemHealthController::class, 'resetCircuitBreaker'])->name('system-health.reset-cb');
    Route::post('/system-health/simulate-webhook', [SystemHealthController::class, 'simulateWebhook'])->name('system-health.simulate-webhook');

    // Profile Image Proxy to bypass hotlinking protection
    Route::get('/profile-image-proxy', function (Request $request) {
        $url = $request->query('url');
        if (empty($url)) {
            return response()->noContent();
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ])->get($url);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', $response->header('Content-Type') ?: 'image/jpeg')
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        } catch (Exception $e) {
            // Fallback
        }

        return response()->noContent();
    })->name('profile-image-proxy');
});

require __DIR__.'/settings.php';
