<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/instagram', [WebhookController::class, 'handleInstagram'])->name('api.webhooks.instagram');
Route::post('/webhooks/youtube', [WebhookController::class, 'handleYoutube'])->name('api.webhooks.youtube');
