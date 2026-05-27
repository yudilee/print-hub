<?php

use App\Http\Controllers\Api\PrintHubController;
use App\Http\Controllers\Api\ClientAppController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AgentVersionController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Print Agent API  (authenticated by agent_key Bearer token)
// ─────────────────────────────────────────────
Route::prefix('print-hub')->middleware(['throttle:120,1', 'throttle.api-key'])->group(function () {
    Route::get('/profiles',                    [PrintHubController::class, 'getProfiles']);
    Route::get('/queue',                       [PrintHubController::class, 'getQueue']);
    Route::post('/jobs',                       [PrintHubController::class, 'reportJob']);
    Route::post('/status',                     [PrintHubController::class, 'updateStatus']);
    Route::get('/cors-origins',                [PrintHubController::class, 'getCorsOrigins']);
    Route::post('/heartbeat',                  [PrintHubController::class, 'heartbeat']);
    Route::get('/agent/version',               [PrintHubController::class, 'getAgentVersion']);
    Route::get('/jobs/{job_id}/download',       [PrintHubController::class, 'downloadJob'])->name('agent.job.download');
    Route::post('/diagnostics/crash',           [PrintHubController::class, 'reportCrash']);
});

// ─────────────────────────────────────────────
// Agent Version API  (open endpoint for agent auto-update checks)
// ─────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    Route::get('/agents/version', [AgentVersionController::class, 'index']);

    // Fonts endpoint for template designer (no auth required for canvas preview)
    Route::get('/fonts', function () {
        return \App\Models\PrintFont::active()->get(['id', 'name', 'font_family', 'font_style', 'file_path']);
    });

    // Formula Editor API  (no auth required — consumed by admin template designer)
    Route::get('/formula/functions', function () {
        return app(\App\Services\FormulaService::class)->getFunctions();
    });
    Route::post('/formula/validate', function (\Illuminate\Http\Request $request) {
        return app(\App\Services\FormulaService::class)->validate($request->input('expression', ''));
    });
    Route::post('/formula/evaluate', function (\Illuminate\Http\Request $request) {
        try {
            $result = app(\App\Services\FormulaService::class)->evaluate(
                $request->input('expression', ''),
                $request->input('data', [])
            );
            return ['result' => $result];
        } catch (\Exception $e) {
            return response()->json(['result' => null, 'error' => $e->getMessage()], 422);
        }
    });
});

// ─────────────────────────────────────────────
// Client Apps API  (authenticated by X-API-Key header)
// ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['throttle:60,1', 'auth.api-key', 'throttle.api-key'])->group(function () {
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
    Route::post('/templates/{name}/validate', [ClientAppController::class, 'validateTemplateData']);

    // Data schema registration & discovery
    Route::post('/schema',                    [ClientAppController::class, 'registerSchema']);
    Route::get('/schemas',                    [ClientAppController::class, 'listSchemas']);
    Route::get('/schema/{name}/versions',     [ClientAppController::class, 'schemaVersions']);
    Route::get('/schemas/{name}/diff',        [ClientAppController::class, 'schemaVersionDiff']);

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

    // Connector Registry  (Phase 2.1)
    Route::get('/connectors',             [ClientAppController::class, 'listConnectors']);
    Route::post('/connectors',            [ClientAppController::class, 'registerConnector']);
    Route::put('/connectors/{id}',        [ClientAppController::class, 'updateConnector']);
    Route::delete('/connectors/{id}',     [ClientAppController::class, 'deleteConnector']);
    Route::post('/connectors/{id}/test',  [ClientAppController::class, 'testConnector']);
    Route::post('/connectors/{id}/fetch-preview', [ClientAppController::class, 'fetchPreview']);
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
