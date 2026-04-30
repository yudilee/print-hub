<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'session.activity'])->group(function () {
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
    Route::post('/jobs/{job}/retry', [AdminController::class, 'jobRetry'])->name('admin.jobs.retry');

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

    // Companies (super-admin)
    Route::get('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('admin.companies');
    Route::post('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'store'])->name('admin.companies.store');
    Route::put('/companies/{company}', [\App\Http\Controllers\Admin\CompanyController::class, 'update'])->name('admin.companies.update');
    Route::delete('/companies/{company}', [\App\Http\Controllers\Admin\CompanyController::class, 'destroy'])->name('admin.companies.destroy');

    // Branches
    Route::get('/branches', [\App\Http\Controllers\Admin\BranchController::class, 'index'])->name('admin.branches');
    Route::post('/branches', [\App\Http\Controllers\Admin\BranchController::class, 'store'])->name('admin.branches.store');
    Route::put('/branches/{branch}', [\App\Http\Controllers\Admin\BranchController::class, 'update'])->name('admin.branches.update');
    Route::delete('/branches/{branch}', [\App\Http\Controllers\Admin\BranchController::class, 'destroy'])->name('admin.branches.destroy');
    Route::get('/branches/{branch}/template-defaults', [\App\Http\Controllers\Admin\BranchController::class, 'templateDefaults'])->name('admin.branches.template-defaults');
    Route::post('/branches/{branch}/template-defaults', [\App\Http\Controllers\Admin\BranchController::class, 'saveTemplateDefaults'])->name('admin.branches.template-defaults.save');

    // Users
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users');
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
    Route::put('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

    // Sessions
    Route::get('/sessions', [\App\Http\Controllers\Admin\SessionController::class, 'index'])->name('admin.sessions');
    Route::delete('/sessions/{id}', [\App\Http\Controllers\Admin\SessionController::class, 'destroy'])->name('admin.sessions.destroy');
    // Activity Log
    Route::get('/activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('admin.activity-logs');

    // SDK Documentation
    Route::get('/sdk-docs', function () {
        return view('admin.sdk-docs');
    })->name('admin.sdk-docs');
});

