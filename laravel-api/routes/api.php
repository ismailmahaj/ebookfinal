<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminEbookController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Ebook\EbookController;
use App\Http\Controllers\Ebook\EbookStreamController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\Subscription\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/storage/covers/{filename}', [StorageController::class, 'cover'])
    ->where('filename', '[a-zA-Z0-9_\-\.]+');

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Subscription management
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/create', [SubscriptionController::class, 'create']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
    });

    // Ebook catalogue (liste et détail)
    Route::get('/ebooks', [EbookController::class, 'index']);
    Route::get('/ebooks/{id}/preview', [EbookStreamController::class, 'streamPreview']);
    Route::get('/ebooks/{id}', [EbookController::class, 'show']);

    // Ebook routes (stream, download, read)

    Route::middleware('subscribed')->group(function () {
        Route::get('/ebooks/{id}/stream', [EbookStreamController::class, 'stream']);
        Route::get('/ebooks/{id}/download', [EbookController::class, 'download']);
        Route::get('/ebooks/{id}/read', [EbookController::class, 'read']);
    });

    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::patch('/users/{id}/subscription', [AdminUserController::class, 'updateSubscription']);
        Route::apiResource('ebooks', AdminEbookController::class);
        Route::post('/ebooks/{id}/toggle-visibility', [AdminEbookController::class, 'toggleVisibility']);
        Route::get('/ebooks/{id}/stats', [AdminEbookController::class, 'stats']);
    });
});

// Webhook route (no authentication, signature verification only)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
