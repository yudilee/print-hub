<?php

use App\Http\Controllers\Api\PrintHubController;
use App\Http\Controllers\Api\ClientAppController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ApprovalController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Print Agent API  (authenticated by agent_key Bearer token)
// ─────────────────────────────────────────────
Route::prefix('print-hub')->middleware('throttle:120,1')->group(function () {
    Route::get('/profiles',      [PrintHubController::class, 'getProfiles']);
    Route::get('/queue',         [PrintHubController::class, 'getQueue']);
    Route::post('/jobs',         [PrintHubController::class, 'reportJob']);
    Route::post('/status',       [PrintHubController::class, 'updateStatus']);
    Route::get('/cors-origins',  [PrintHubController::class, 'getCorsOrigins']);
    Route::post('/heartbeat',    [PrintHubController::class, 'heartbeat']);
    Route::get('/agent/version', [PrintHubController::class, 'getAgentVersion']);
});

// ─────────────────────────────────────────────
// Agent Version API  (open endpoint or key-authenticated)
// ─────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    Route::get('/agents/version', [PrintHubController::class, 'getAgentVersion']);
});

// ─────────────────────────────────────────────
// Client Apps API  (authenticated by X-API-Key header)
// ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['throttle:60,1', 'auth.api-key'])->group(function () {
    // Test Connection
    Route::get('/test', [ClientAppController::class, 'testConnection']);

    // Discovery
    Route::get('/agents/online', [ClientAppController::class, 'getOnlineAgents']);
    Route::get('/branches',      [ClientAppController::class, 'listBranches']);
    Route::get('/queues',        [ClientAppController::class, 'listQueues']);

    // Template discovery
    Route::get('/templates',            [ClientAppController::class, 'listTemplates']);
    Route::get('/templates/{name}',     [ClientAppController::class, 'getTemplate']);
    Route::get('/templates/{name}/schema', [ClientAppController::class, 'getTemplateSchema']);

    // Data schema registration & discovery
    Route::post('/schema',                    [ClientAppController::class, 'registerSchema']);
    Route::get('/schemas',                    [ClientAppController::class, 'listSchemas']);
    Route::get('/schema/{name}/versions',     [ClientAppController::class, 'schemaVersions']);

    // Print endpoints
    Route::post('/print',        [ClientAppController::class, 'unifiedPrint']);
    Route::post('/print/batch',  [ClientAppController::class, 'batchPrint']);
    Route::post('/preview',      [ClientAppController::class, 'previewPrint']);

    // Job management
    Route::post('/jobs',              [ClientAppController::class, 'submitJob']);    // legacy
    Route::get('/jobs/{job_id}',      [ClientAppController::class, 'jobStatus']);
    Route::delete('/jobs/{job_id}',   [ClientAppController::class, 'cancelJob']);

    // Health
    Route::get('/health', [ClientAppController::class, 'health']);
    // Document Management (Feature 2)
    Route::post('/documents/upload',       [DocumentController::class, 'upload']);
    Route::get('/documents',               [DocumentController::class, 'list']);
    Route::get('/documents/{id}',          [DocumentController::class, 'show']);
    Route::get('/documents/{id}/preview',  [DocumentController::class, 'preview'])->name('api.documents.preview');
    Route::get('/documents/{id}/download', [DocumentController::class, 'download'])->name('api.documents.download');
    Route::delete('/documents/{id}',       [DocumentController::class, 'destroy']);
});

// ─────────────────────────────────────────────
// Approval API  (authenticated by Sanctum + admin role)
// ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('approvals')->group(function () {
    Route::get('/pending',        [ApprovalController::class, 'pendingJobs']);
    Route::post('/{id}/approve',  [ApprovalController::class, 'approve']);
    Route::post('/{id}/reject',   [ApprovalController::class, 'reject']);
});
