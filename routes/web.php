<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

// Agents
Route::get('/agents', [AdminController::class, 'agentsIndex'])->name('admin.agents');
Route::post('/agents', [AdminController::class, 'agentStore'])->name('admin.agents.store');
Route::delete('/agents/{agent}', [AdminController::class, 'agentDestroy'])->name('admin.agents.destroy');

// Profiles
Route::get('/profiles', [AdminController::class, 'profilesIndex'])->name('admin.profiles');
Route::post('/profiles', [AdminController::class, 'profileStore'])->name('admin.profiles.store');
Route::delete('/profiles/{profile}', [AdminController::class, 'profileDestroy'])->name('admin.profiles.destroy');
Route::post('/profiles/{profile}/test-print', [AdminController::class, 'profileTestPrint'])->name('admin.profiles.test-print');
Route::get('/profiles/{profile}/edit', [AdminController::class, 'profileEdit'])->name('admin.profiles.edit');
Route::put('/profiles/{profile}', [AdminController::class, 'profileUpdate'])->name('admin.profiles.update');

// Templates
Route::get('/templates', [AdminController::class, 'templatesIndex'])->name('admin.templates');
Route::get('/templates/create', [AdminController::class, 'templateCreate'])->name('admin.templates.create');
Route::post('/templates', [AdminController::class, 'templateStore'])->name('admin.templates.store');
Route::get('/templates/{template}/edit', [AdminController::class, 'templateEdit'])->name('admin.templates.edit');
Route::put('/templates/{template}', [AdminController::class, 'templateUpdate'])->name('admin.templates.update');
Route::delete('/templates/{template}', [AdminController::class, 'templateDestroy'])->name('admin.templates.destroy');
Route::post('/templates/upload-bg', [AdminController::class, 'templateUploadBg'])->name('admin.templates.upload-bg');
Route::post('/templates/preview', [AdminController::class, 'templatePreview'])->name('admin.templates.preview');
Route::post('/templates/test-print', [AdminController::class, 'templateTestPrint'])->name('admin.templates.test-print');
Route::post('/templates/{template}/clone', [AdminController::class, 'templateClone'])->name('admin.templates.clone');
Route::get('/templates/{template}/job-history', [AdminController::class, 'templateJobHistory'])->name('admin.templates.job-history');

// Job History
Route::get('/jobs', [AdminController::class, 'jobsIndex'])->name('admin.jobs');
Route::get('/jobs/{job}/download', [AdminController::class, 'downloadDocument'])->name('admin.jobs.download');
Route::post('/jobs/{job}/status', [AdminController::class, 'updateJobStatus'])->name('admin.jobs.status');

// Client Apps
Route::get('/clients', [AdminController::class, 'clientsIndex'])->name('admin.clients');
Route::post('/clients', [AdminController::class, 'clientStore'])->name('admin.clients.store');
Route::delete('/clients/{client}', [AdminController::class, 'clientDestroy'])->name('admin.clients.destroy');
Route::get('/clients/sdk', function () {
    return response()->file(public_path('sdk/PrintHubClient.php'), [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="PrintHubClient.php"',
    ]);
})->name('admin.clients.sdk');

