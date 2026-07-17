<?php

use App\Http\Controllers\Api\AdvertisingController;
use App\Http\Controllers\Api\AlertRuleController;
use App\Http\Controllers\Api\AmazonIntegrationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompetitorController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExperimentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\KeywordProjectController;
use App\Http\Controllers\Api\ListingAuditController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
    Route::get('/integrations/amazon/callback', [AmazonIntegrationController::class, 'callback'])
        ->middleware('throttle:amazon-oauth');

    Route::middleware(['auth:sanctum', 'organization', 'throttle:api-writes'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', DashboardController::class);
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}/refresh-amazon', [ProductController::class, 'refreshAmazon']);

        Route::get('/listings', [ListingController::class, 'index']);
        Route::get('/listings/{listing}', [ListingController::class, 'show']);
        Route::put('/listings/{listing}', [ListingController::class, 'update']);
        Route::post('/listings/{listing}/refresh-amazon', [ListingController::class, 'refreshAmazon']);
        Route::post('/listings/{listing}/amazon/preview', [ListingController::class, 'previewAmazon']);
        Route::post('/listings/{listing}/amazon/publish', [ListingController::class, 'publishAmazon']);
        Route::post('/listings/{listing}/audits', [ListingAuditController::class, 'store']);

        Route::get('/keyword-projects', [KeywordProjectController::class, 'index']);
        Route::post('/keyword-projects', [KeywordProjectController::class, 'store']);
        Route::post('/keyword-projects/{keywordProject}/keywords', [KeywordProjectController::class, 'addKeywords']);
        Route::apiResource('tasks', TaskController::class)->only(['index', 'store', 'update']);
        Route::apiResource('competitors', CompetitorController::class)->only(['index', 'store', 'update']);
        Route::get('/advertising', [AdvertisingController::class, 'index']);
        Route::apiResource('alert-rules', AlertRuleController::class)->only(['index', 'store', 'update']);
        Route::apiResource('reports', ReportController::class)->only(['index', 'store']);
        Route::apiResource('experiments', ExperimentController::class)->only(['index', 'store', 'update']);

        Route::get('/marketplaces', [AmazonIntegrationController::class, 'marketplaces']);
        Route::get('/integrations/channels', [ChannelController::class, 'index']);
        Route::get('/integrations/amazon/accounts', [AmazonIntegrationController::class, 'index']);
        Route::post('/integrations/amazon/authorize', [AmazonIntegrationController::class, 'authorizeSeller'])
            ->middleware('throttle:amazon-oauth');
        Route::post('/integrations/amazon/accounts/{amazonAccount}/sync', [AmazonIntegrationController::class, 'sync']);
        Route::delete('/integrations/amazon/accounts/{amazonAccount}', [AmazonIntegrationController::class, 'disconnect']);
    });
});
