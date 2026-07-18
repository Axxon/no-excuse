<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\IntegrationIntakeController;
use App\Http\Controllers\MetaController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/activate', [AuthController::class, 'activate'])->middleware('throttle:5,1');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,10');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,10');
Route::get('/about', [MetaController::class, 'about']);
Route::get('/demo', [DemoController::class, 'status']);
Route::post('/demo/sessions', [DemoController::class, 'store']);
Route::post('/demo/waitlist', [DemoController::class, 'waitlist'])->middleware('throttle:3,10');
Route::get('/setup/status', [SetupController::class, 'status']);
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:5,1');

Route::post('/v1/intake/{offer:public_id}/applications', IntegrationIntakeController::class)
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::get('/meta/ai-providers', [MetaController::class, 'aiProviders']);
    Route::get('/operations/status', OperationsController::class);
    Route::post('/demo/reset', [DemoController::class, 'reset']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/mfa', [AuthController::class, 'configureMfa']);
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'update']);
    Route::get('/organization/members', [OrganizationController::class, 'members']);
    Route::post('/organization/members', [OrganizationController::class, 'storeMember']);
    Route::post('/organization/members/{member:public_id}/resend-invitation', [OrganizationController::class, 'resendMemberInvitation']);
    Route::delete('/organization/members/{member:public_id}', [OrganizationController::class, 'destroyMember']);
    Route::apiResource('offers', OfferController::class)->except('destroy');
    Route::post('/offers/{offer}/open', [OfferController::class, 'open']);
    Route::post('/offers/{offer}/close', [OfferController::class, 'close']);
    Route::post('/offers/{offer}/ingestion-key', [OfferController::class, 'rotateIngestionKey']);
    Route::get('/offers/{offer}/applications', [ApplicationController::class, 'index']);
    Route::put('/offers/{offer}/applications/reorder', [ApplicationController::class, 'reorder']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::get('/applications/{application}/cv', [ApplicationController::class, 'cv']);
    Route::get('/applications/{application}/decision-preview', [ApplicationController::class, 'decisionPreview']);
    Route::get('/applications/{application}/data-export', [ApplicationController::class, 'dataExport']);
    Route::delete('/applications/{application}/personal-data', [ApplicationController::class, 'erasePersonalData']);
    Route::post('/applications/{application}/annotations', [ApplicationController::class, 'annotate']);
    Route::put('/applications/{application}/feedback', [ApplicationController::class, 'feedback']);
    Route::post('/applications/{application}/screening-decision', [ApplicationController::class, 'screeningDecision']);
    Route::post('/applications/{application}/retry', [ApplicationController::class, 'retry']);
    Route::post('/applications/{application}/notification-retry', [ApplicationController::class, 'retryNotification']);
    Route::post('/applications/{application}/select', [ApplicationController::class, 'select']);
});
