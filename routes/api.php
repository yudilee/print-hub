<?php

use App\Http\Controllers\Api\PrintHubController;
use App\Http\Controllers\Api\ClientAppController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Print Agent API  (authenticated by agent_key Bearer token)
// ─────────────────────────────────────────────
Route::prefix('print-hub')->group(function () {
    Route::get('/profiles', [PrintHubController::class, 'getProfiles']);
    Route::get('/queue',    [PrintHubController::class, 'getQueue']);
    Route::post('/jobs',    [PrintHubController::class, 'reportJob']);
    Route::post('/status',  [PrintHubController::class, 'updateStatus']);
});

// ─────────────────────────────────────────────
// Client Apps API  (authenticated by X-API-Key header)
// ─────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    // Test Connection
    Route::get('/test', [ClientAppController::class, 'testConnection']);

    // Discovery (no auth needed for agent list)
    Route::get('/agents/online', [ClientAppController::class, 'getOnlineAgents']);

    // Template discovery
    Route::get('/templates',       [ClientAppController::class, 'listTemplates']);
    Route::get('/templates/{name}', [ClientAppController::class, 'getTemplate']);

    // Unified print endpoint
    Route::post('/print', [ClientAppController::class, 'unifiedPrint']);

    // Legacy submit (backwards compat)
    Route::post('/jobs', [ClientAppController::class, 'submitJob']);

    // Job status check
    Route::get('/jobs/{job_id}', [ClientAppController::class, 'jobStatus']);
});
