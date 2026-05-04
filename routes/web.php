<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\ApprovalController as AdminApprovalController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ClientAppController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Single Sign-On (SSO) routes
Route::prefix('auth/sso')->group(function () {
    Route::get('/', [\App\Http\Controllers\Auth\SsoController::class, 'login'])->name('sso.login');
    Route::post('/callback', [\App\Http\Controllers\Auth\SsoController::class, 'callback'])->name('sso.callback');
    Route::get('/metadata', [\App\Http\Controllers\Auth\SsoController::class, 'metadata'])->name('sso.metadata');
});

// Password Reset
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $status = Password::sendResetLink($request->only('email'));
    return $status === Password::RESET_LINK_SENT
        ? back()->with(['status' => __($status)])
        : back()->withErrors(['email' => __($status)]);
})->name('password.email');
Route::get('/reset-password/{token}', function (string $token) {
    return view('auth.reset-password', ['token' => $token]);
})->name('password.reset');
Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);
    $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        }
    );
    return $status === Password::PASSWORD_RESET
        ? redirect()->route('login')->with('status', __($status))
        : back()->withErrors(['email' => [__($status)]]);
})->name('password.update');

Route::middleware(['auth', 'session.activity'])->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Agents
    Route::get('/agents', [AgentController::class, 'index'])->name('admin.agents');
    Route::post('/agents', [AgentController::class, 'store'])->name('admin.agents.store');
    Route::put('/agents/{agent}', [AgentController::class, 'update'])->name('admin.agents.update');
    Route::post('/agents/{agent}/regenerate-key', [AgentController::class, 'regenerateKey'])->name('admin.agents.regenerate-key');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('admin.agents.destroy');

    // Profiles
    Route::get('/profiles', [ProfileController::class, 'index'])->name('admin.profiles');
    Route::post('/profiles', [ProfileController::class, 'store'])->name('admin.profiles.store');
    Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy'])->name('admin.profiles.destroy');
    Route::post('/profiles/{profile}/test-print', [ProfileController::class, 'testPrint'])->name('admin.profiles.test-print');
    Route::get('/profiles/{profile}/edit', [ProfileController::class, 'edit'])->name('admin.profiles.edit');
    Route::put('/profiles/{profile}', [ProfileController::class, 'update'])->name('admin.profiles.update');

    // Templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('admin.templates');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('admin.templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('admin.templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('admin.templates.edit');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('admin.templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('admin.templates.destroy');
    Route::post('/templates/upload-bg', [TemplateController::class, 'uploadBg'])->name('admin.templates.upload-bg');
    Route::post('/templates/preview', [TemplateController::class, 'preview'])->name('admin.templates.preview');
    Route::post('/templates/test-print', [TemplateController::class, 'testPrint'])->name('admin.templates.test-print');
    Route::post('/templates/{template}/clone', [TemplateController::class, 'clone'])->name('admin.templates.clone');
    Route::get('/templates/{template}/job-history', [TemplateController::class, 'jobHistory'])->name('admin.templates.job-history');

    // Job History
    Route::get('/jobs', [JobController::class, 'index'])->name('admin.jobs');
    Route::get('/jobs/{job}/download', [JobController::class, 'download'])->name('admin.jobs.download');
    Route::post('/jobs/{job}/status', [JobController::class, 'updateStatus'])->name('admin.jobs.status');
    Route::post('/jobs/{job}/retry', [JobController::class, 'retry'])->name('admin.jobs.retry');

    // Client Apps
    Route::get('/clients', [ClientAppController::class, 'index'])->name('admin.clients');
    Route::post('/clients', [ClientAppController::class, 'store'])->name('admin.clients.store');
    Route::post('/clients/{client}/regenerate-key', [ClientAppController::class, 'regenerateKey'])->name('admin.clients.regenerate-key');
    Route::delete('/clients/{client}', [ClientAppController::class, 'destroy'])->name('admin.clients.destroy');
    Route::get('/clients/sdk', function () {
        return response()->file(public_path('sdk/PrintHubClient.php'), [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="PrintHubClient.php"',
        ]);
    })->name('admin.clients.sdk');

    // Companies (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index'])->name('admin.companies');
        Route::post('/companies', [CompanyController::class, 'store'])->name('admin.companies.store');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('admin.companies.update');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('admin.companies.destroy');
    });

    // Branches
    Route::get('/branches', [BranchController::class, 'index'])->name('admin.branches');
    Route::post('/branches', [BranchController::class, 'store'])->name('admin.branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('admin.branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('admin.branches.destroy');
    Route::get('/branches/{branch}/template-defaults', [BranchController::class, 'templateDefaults'])->name('admin.branches.template-defaults');
    Route::post('/branches/{branch}/template-defaults', [BranchController::class, 'saveTemplateDefaults'])->name('admin.branches.template-defaults.save');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('admin.users');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

    // Sessions
    Route::get('/sessions', [SessionController::class, 'index'])->name('admin.sessions');
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->name('admin.sessions.destroy');

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');

    // Documents (Feature 2)
    Route::get('/documents', [AdminDocumentController::class, 'index'])->name('admin.documents');
    Route::post('/documents/upload', [AdminDocumentController::class, 'upload'])->name('admin.documents.upload');
    Route::delete('/documents/{id}', [AdminDocumentController::class, 'destroy'])->name('admin.documents.destroy');

    // Monitoring Dashboard (Feature 5)
    Route::get('/monitoring', [\App\Http\Controllers\Admin\MonitoringController::class, 'index'])->name('admin.monitoring');
    Route::get('/monitoring/stats', [\App\Http\Controllers\Admin\MonitoringController::class, 'stats'])->name('admin.monitoring.stats');
    Route::get('/monitoring/agent-health', [\App\Http\Controllers\Admin\MonitoringController::class, 'agentHealth'])->name('admin.monitoring.agent-health');
    Route::get('/monitoring/job-timeline', [\App\Http\Controllers\Admin\MonitoringController::class, 'jobTimeline'])->name('admin.monitoring.job-timeline');

    // Approvals (Feature 3)
    Route::get('/approvals', [AdminApprovalController::class, 'index'])->name('admin.approvals');
    Route::post('/approvals/{id}/approve', [AdminApprovalController::class, 'approve'])->name('admin.approvals.approve');
    Route::post('/approvals/{id}/reject', [AdminApprovalController::class, 'reject'])->name('admin.approvals.reject');

    // SDK Documentation
    Route::get('/sdk-docs', function () {
        return view('admin.sdk-docs');
    })->name('admin.sdk-docs');

    // Printer Pools (Feature 5)
    Route::get('/pools', [\App\Http\Controllers\Admin\PoolController::class, 'index'])->name('admin.pools');
    Route::get('/pools/create', [\App\Http\Controllers\Admin\PoolController::class, 'edit'])->name('admin.pools.create');
    Route::post('/pools', [\App\Http\Controllers\Admin\PoolController::class, 'store'])->name('admin.pools.store');
    Route::get('/pools/{pool}/edit', [\App\Http\Controllers\Admin\PoolController::class, 'edit'])->name('admin.pools.edit');
    Route::put('/pools/{pool}', [\App\Http\Controllers\Admin\PoolController::class, 'update'])->name('admin.pools.update');
    Route::delete('/pools/{pool}', [\App\Http\Controllers\Admin\PoolController::class, 'destroy'])->name('admin.pools.destroy');

    // SSO Settings (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/sso', function () {
            return view('admin.sso.index');
        })->name('admin.sso-settings');
    });

    // IP Whitelist settings page (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/ip-whitelist', function () {
            return view('admin.ip-whitelist');
        })->name('admin.ip-whitelist');
    });
});
