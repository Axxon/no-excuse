<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IntegrationIntakeController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/setup/status', [SetupController::class, 'status']);
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1');

Route::post('/v1/intake/{offer:public_id}/applications', IntegrationIntakeController::class)
    ->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/meta/ai-providers', [MetaController::class, 'aiProviders']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'update']);
    Route::get('/organization/members', [OrganizationController::class, 'members']);
    Route::post('/organization/members', [OrganizationController::class, 'storeMember']);
    Route::apiResource('offers', OfferController::class)->except('destroy');
    Route::post('/offers/{offer}/open', [OfferController::class, 'open']);
    Route::post('/offers/{offer}/close', [OfferController::class, 'close']);
    Route::post('/offers/{offer}/ingestion-key', [OfferController::class, 'rotateIngestionKey']);
    Route::get('/offers/{offer}/applications', [ApplicationController::class, 'index']);
    Route::put('/offers/{offer}/applications/reorder', [ApplicationController::class, 'reorder']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::get('/applications/{application}/cv', [ApplicationController::class, 'cv']);
    Route::post('/applications/{application}/annotations', [ApplicationController::class, 'annotate']);
    Route::put('/applications/{application}/feedback', [ApplicationController::class, 'feedback']);
    Route::post('/applications/{application}/select', [ApplicationController::class, 'select']);
});
