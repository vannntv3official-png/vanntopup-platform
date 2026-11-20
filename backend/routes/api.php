<?php

use App\Http\Controllers\Api\V1\Admin\AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminOperationsController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminSupplierController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\GoogleAuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Health
    Route::get('/health', HealthController::class);

    // Public Catalog
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/games', [CatalogController::class, 'games']);
    Route::get('/games/{slug}', [CatalogController::class, 'showGame']);
    Route::post('/games/{slug}/validate-player', [CatalogController::class, 'validatePlayer'])
        ->middleware('throttle:30,1');

    // Server-signed Quotes & Guest/Customer Checkout
    Route::post('/quotes', [QuoteController::class, 'store'])->middleware('throttle:60,1');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/orders/{orderNumber}/track', [OrderController::class, 'track'])->middleware('throttle:60,1');
    Route::post('/orders/{orderNumber}/payment-sessions', [PaymentController::class, 'createSession'])->middleware('throttle:30,1');

    // Dev/Testing-Only Deterministic Mock Payment Trigger (blocked in production)
    Route::post('/payments/{paymentIntentId}/simulate', [PaymentController::class, 'simulateMockOutcome']);

    // Payment Webhooks (server-to-server)
    Route::post('/webhooks/payments/{provider}', [WebhookController::class, 'handlePaymentWebhook'])
        ->middleware('throttle:120,1');

    // Public Support Ticket Creation (Guest or Authenticated)
    Route::post('/support/tickets', [SupportController::class, 'store'])->middleware('throttle:15,1');

    // Auth
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1');
        Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1');
        Route::post('/google/dev-login', [GoogleAuthController::class, 'devLogin'])->middleware('throttle:20,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::patch('/profile', [AuthController::class, 'updateProfile']);
            Route::post('/mfa/setup', [AuthController::class, 'setupMfa']);
            Route::post('/mfa/confirm', [AuthController::class, 'confirmMfa']);
        });
    });

    // Authenticated Customer Routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/account/orders', [OrderController::class, 'index']);
        Route::get('/account/tickets', [SupportController::class, 'index']);
    });

    // Protected Admin Operations API (RBAC)
    Route::prefix('admin')->middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/overview', [AdminDashboardController::class, 'overview'])
            ->middleware('permission:dashboard.view');
        Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs'])
            ->middleware('permission:audit.view');

        Route::get('/orders', [AdminOrderController::class, 'index'])
            ->middleware('permission:orders.view');
        Route::get('/orders/{id}', [AdminOrderController::class, 'show'])
            ->middleware('permission:orders.view');
        Route::post('/orders/{id}/retry-fulfillment', [AdminOrderController::class, 'retryFulfillment'])
            ->middleware('permission:orders.manage');
        Route::post('/orders/{id}/resolve-review', [AdminOrderController::class, 'resolveReview'])
            ->middleware('permission:orders.manage');
        Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund'])
            ->middleware('permission:payments.refund');

        Route::get('/catalog/games', [AdminCatalogController::class, 'games'])
            ->middleware('permission:catalog.view');
        Route::patch('/catalog/games/{id}', [AdminCatalogController::class, 'updateGame'])
            ->middleware('permission:catalog.manage');
        Route::patch('/catalog/packages/{id}', [AdminCatalogController::class, 'updatePackage'])
            ->middleware('permission:catalog.manage');

        Route::get('/suppliers', [AdminSupplierController::class, 'index'])
            ->middleware('permission:suppliers.view');
        Route::patch('/suppliers/{id}', [AdminSupplierController::class, 'update'])
            ->middleware('permission:suppliers.manage');
        Route::post('/suppliers/{id}/health-check', [AdminSupplierController::class, 'runHealthCheck'])
            ->middleware('permission:suppliers.manage');

        Route::get('/payments', [AdminOperationsController::class, 'payments'])
            ->middleware('permission:payments.view');
        Route::get('/reconciliation/discrepancies', [AdminOperationsController::class, 'discrepancies'])
            ->middleware('permission:reconciliation.view');
        Route::post('/reconciliation/run', [AdminOperationsController::class, 'runReconciliation'])
            ->middleware('permission:reconciliation.manage');
        Route::patch('/reconciliation/discrepancies/{id}', [AdminOperationsController::class, 'updateDiscrepancy'])
            ->middleware('permission:reconciliation.manage');

        Route::get('/promotions/coupons', [AdminOperationsController::class, 'coupons'])
            ->middleware('permission:promotions.view');
        Route::post('/promotions/coupons', [AdminOperationsController::class, 'storeCoupon'])
            ->middleware('permission:promotions.manage');

        Route::get('/customers', [AdminOperationsController::class, 'customers'])
            ->middleware('permission:customers.view');

        Route::get('/settings', [AdminOperationsController::class, 'settings'])
            ->middleware('permission:settings.view');
        Route::patch('/settings', [AdminOperationsController::class, 'updateSettings'])
            ->middleware('permission:settings.manage');
    });
});
