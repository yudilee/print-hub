<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\ApprovalController as AdminApprovalController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ClientAppController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\FontController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PrinterConfigController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReleaseController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebhookController;
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
    Route::get('/agents/{agent}/activity', [AgentController::class, 'activity'])->name('admin.agents.activity');

    // Printer Configs (Item 8.1)
    Route::get('/printer-configs', [PrinterConfigController::class, 'index'])->name('admin.printer-configs');
    Route::post('/printer-configs', [PrinterConfigController::class, 'store'])->name('admin.printer-configs.store');
    Route::get('/printer-configs/{printerConfig}/edit', [PrinterConfigController::class, 'edit'])->name('admin.printer-configs.edit');
    Route::put('/printer-configs/{printerConfig}', [PrinterConfigController::class, 'update'])->name('admin.printer-configs.update');
    Route::delete('/printer-configs/{printerConfig}', [PrinterConfigController::class, 'destroy'])->name('admin.printer-configs.destroy');

    // Profiles
    Route::get('/profiles', [ProfileController::class, 'index'])->name('admin.profiles');
    Route::post('/profiles', [ProfileController::class, 'store'])->name('admin.profiles.store');
    Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy'])->name('admin.profiles.destroy');
    Route::post('/profiles/{profile}/test-print', [ProfileController::class, 'testPrint'])->name('admin.profiles.test-print');
    Route::get('/profiles/{profile}/edit', [ProfileController::class, 'edit'])->name('admin.profiles.edit');
    Route::put('/profiles/{profile}', [ProfileController::class, 'update'])->name('admin.profiles.update');
    Route::get('/profiles/{profile}/clone', [ProfileController::class, 'clone'])->name('admin.profiles.clone');

    // Templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('admin.templates');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('admin.templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->name('admin.templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('admin.templates.edit');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('admin.templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->name('admin.templates.destroy');
    Route::post('/templates/upload-bg', [TemplateController::class, 'uploadBg'])->name('admin.templates.upload-bg');
    Route::post('/templates/preview', [TemplateController::class, 'preview'])->name('admin.templates.preview');
    Route::match(['GET', 'POST'], '/templates/{template}/preview', [TemplateController::class, 'preview'])->name('admin.templates.preview-with-template');
    Route::post('/templates/test-print', [TemplateController::class, 'testPrint'])->name('admin.templates.test-print');
    Route::post('/templates/{template}/clone', [TemplateController::class, 'clone'])->name('admin.templates.clone');
    Route::get('/templates/{template}/job-history', [TemplateController::class, 'jobHistory'])->name('admin.templates.job-history');
    Route::post('/templates/{template}/sample-data', [TemplateController::class, 'saveSampleData'])->name('admin.templates.sample-data.save');
    Route::get('/templates/{template}/sample-data', [TemplateController::class, 'getSampleData'])->name('admin.templates.sample-data.get');
    Route::put('/templates/{template}/schemas', [TemplateController::class, 'saveSchemas'])->name('admin.templates.schemas.save');

    // Test Scenarios (Phase 4.2)
    Route::get('/templates/{template}/scenarios', [TemplateController::class, 'listScenarios'])->name('admin.templates.scenarios.list');
    Route::post('/templates/{template}/scenarios', [TemplateController::class, 'storeScenario'])->name('admin.templates.scenarios.store');
    Route::put('/templates/{template}/scenarios/{scenario}', [TemplateController::class, 'updateScenario'])->name('admin.templates.scenarios.update');
    Route::delete('/templates/{template}/scenarios/{scenario}', [TemplateController::class, 'deleteScenario'])->name('admin.templates.scenarios.delete');
    Route::post('/templates/{template}/scenarios/{scenario}/set-default', [TemplateController::class, 'setDefaultScenario'])->name('admin.templates.scenarios.set-default');

    // Template Version History
    Route::get('/templates/{template}/versions', [TemplateController::class, 'versions'])->name('templates.versions');
    Route::post('/templates/{template}/versions', [TemplateController::class, 'createVersion'])->name('templates.versions.create');
    Route::post('/templates/{template}/versions/{version}/restore', [TemplateController::class, 'restoreVersion'])->name('templates.versions.restore');
    Route::get('/templates/{template}/versions/{v1}/diff/{v2}', [TemplateController::class, 'diffVersions'])->name('templates.versions.diff');

    // Custom Fonts Management
    Route::resource('fonts', FontController::class)->names([
        'index'   => 'admin.fonts',
        'store'   => 'admin.fonts.store',
        'update'  => 'admin.fonts.update',
        'destroy' => 'admin.fonts.destroy',
    ])->except(['show', 'edit']);
    Route::get('/fonts/{font}/download', [FontController::class, 'download'])->name('admin.fonts.download');
    Route::get('/fonts/{font}/preview', [FontController::class, 'preview'])->name('admin.fonts.preview');

    // Job History
    Route::get('/jobs', [JobController::class, 'index'])->name('admin.jobs');
    Route::get('/jobs/export', [JobController::class, 'exportCsv'])->name('admin.jobs.export');
    Route::get('/jobs/{job}/download', [JobController::class, 'download'])->name('admin.jobs.download');
    Route::post('/jobs/{job}/status', [JobController::class, 'updateStatus'])->name('admin.jobs.status');
    Route::post('/jobs/{job}/retry', [JobController::class, 'retry'])->name('admin.jobs.retry');
    Route::post('/jobs/retry-all-failed', [JobController::class, 'retryAllFailed'])->name('admin.jobs.retry-all-failed');
    Route::post('/jobs/bulk-retry', [JobController::class, 'bulkRetry'])->name('admin.jobs.bulk-retry');
    Route::get('/jobs/{job}/dependencies', [JobController::class, 'dependencies'])->name('admin.jobs.dependencies');
    Route::get('/jobs/search-parents', [JobController::class, 'searchParentJobs'])->name('admin.jobs.search-parents');
    Route::post('/jobs/validate-dependency', [JobController::class, 'validateDependency'])->name('admin.jobs.validate-dependency');
    Route::post('/jobs/{job}/update-dependency', [JobController::class, 'updateDependency'])->name('admin.jobs.update-dependency');
    Route::get('/jobs/{job}/preview', [JobController::class, 'preview'])->name('admin.jobs.preview');

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
    Route::post('/sessions/force-logout-all', [SessionController::class, 'forceLogoutAll'])->name('admin.sessions.force-logout-all');
    Route::post('/sessions/force-logout-user/{user}', [SessionController::class, 'forceLogoutUser'])->name('admin.sessions.force-logout-user');

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs');
    Route::get('/activity-logs/export', [ActivityLogController::class, 'exportCsv'])->name('admin.activity-logs.export');

    // Notifications (Item 18.1)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('admin.notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('admin.notifications.mark-all-read');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('admin.notifications.unread-count');

    // Documents (Feature 2)
    Route::get('/documents', [AdminDocumentController::class, 'index'])->name('admin.documents');
    Route::post('/documents/upload', [AdminDocumentController::class, 'upload'])->name('admin.documents.upload');
    Route::post('/documents/purge-expired', [AdminDocumentController::class, 'purgeExpired'])->name('admin.documents.purge-expired');
    Route::get('/documents/{id}/versions', [AdminDocumentController::class, 'versions'])->name('admin.documents.versions');
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

    // Cost Tracking (Feature 1)
    Route::get('/costs', [\App\Http\Controllers\Admin\CostController::class, 'index'])->name('admin.costs');
    Route::get('/costs/export', [\App\Http\Controllers\Admin\CostController::class, 'exportCsv'])->name('admin.costs.export');

    // Printer Pools (Feature 5)
    Route::get('/pools', [\App\Http\Controllers\Admin\PoolController::class, 'index'])->name('admin.pools');
    Route::get('/pools/create', [\App\Http\Controllers\Admin\PoolController::class, 'edit'])->name('admin.pools.create');
    Route::post('/pools', [\App\Http\Controllers\Admin\PoolController::class, 'store'])->name('admin.pools.store');
    Route::get('/pools/{pool}/edit', [\App\Http\Controllers\Admin\PoolController::class, 'edit'])->name('admin.pools.edit');
    Route::put('/pools/{pool}', [\App\Http\Controllers\Admin\PoolController::class, 'update'])->name('admin.pools.update');
    Route::delete('/pools/{pool}', [\App\Http\Controllers\Admin\PoolController::class, 'destroy'])->name('admin.pools.destroy');
    Route::post('/pools/{pool}/reset-health', [\App\Http\Controllers\Admin\PoolController::class, 'resetHealth'])->name('admin.pools.reset-health');

    // SSO Settings (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/sso', function () {
            return view('admin.sso.index');
        })->name('admin.sso-settings');
    });
    
    // Prometheus Metrics Endpoint (Task 3.5) — accessible without auth for monitoring systems
    Route::get('/metrics', [\App\Http\Controllers\MetricsController::class, 'index']);
    

    // IP Whitelist settings page (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/ip-whitelist', [\App\Http\Controllers\Admin\IpWhitelistController::class, 'index'])->name('admin.ip-whitelist');
        Route::post('/ip-whitelist/client-app/{clientApp}', [\App\Http\Controllers\Admin\IpWhitelistController::class, 'updateClientApp'])->name('admin.ip-whitelist.client-app');
        Route::post('/ip-whitelist/agent/{agent}', [\App\Http\Controllers\Admin\IpWhitelistController::class, 'updateAgent'])->name('admin.ip-whitelist.agent');
    });

    // System Settings (Item 10.1)
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings');
    Route::put('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('admin.settings.update');

    // Webhook Settings
    Route::get('/settings/webhooks', [WebhookController::class, 'index'])->name('admin.webhooks.index');
    Route::put('/settings/webhooks/{clientApp}', [WebhookController::class, 'update'])->name('admin.webhooks.update');
    Route::get('/settings/webhooks/{clientApp}/deliveries', [WebhookController::class, 'deliveries'])->name('admin.webhooks.deliveries');
    Route::post('/settings/webhooks/deliveries/{delivery}/retry', [WebhookController::class, 'retryDelivery'])->name('admin.webhooks.deliveries.retry');
    Route::post('/settings/webhooks/{clientApp}/deliveries/bulk-retry', [WebhookController::class, 'bulkRetry'])->name('admin.webhooks.deliveries.bulk-retry');
    Route::get('/settings/webhooks/{clientApp}/deliveries/export-csv', [WebhookController::class, 'exportDeliveriesCsv'])->name('admin.webhooks.deliveries.export-csv');

    // Agent Releases (Auto-Update from Hub)
    Route::get('/releases', [ReleaseController::class, 'index'])->name('admin.releases');
    Route::post('/releases', [ReleaseController::class, 'store'])->name('admin.releases.store');
    Route::delete('/releases/{release}', [ReleaseController::class, 'destroy'])->name('admin.releases.destroy');
    Route::post('/releases/{release}/mark-latest', [ReleaseController::class, 'markLatest'])->name('admin.releases.mark-latest');

    // ─────────────────────────────────────────────────────────
    //  Scheduled Jobs UI (Task 2)
    // ─────────────────────────────────────────────────────────
    Route::get('/scheduled-jobs', [\App\Http\Controllers\Admin\ScheduledJobController::class, 'index'])->name('admin.scheduled-jobs.index');
    Route::get('/scheduled-jobs/create', [\App\Http\Controllers\Admin\ScheduledJobController::class, 'create'])->name('admin.scheduled-jobs.create');
    Route::post('/scheduled-jobs', [\App\Http\Controllers\Admin\ScheduledJobController::class, 'store'])->name('admin.scheduled-jobs.store');
    Route::delete('/scheduled-jobs/{job}', [\App\Http\Controllers\Admin\ScheduledJobController::class, 'destroy'])->name('admin.scheduled-jobs.destroy');

    // ─────────────────────────────────────────────────────────
    //  API Documentation UI (Task 7 — Swagger)
    // ─────────────────────────────────────────────────────────
    Route::get('/api-docs', function () {
        return view('admin.api-docs');
    })->name('admin.api-docs');

    // ─────────────────────────────────────────────────────────
    //  Two-Factor Authentication (Task 6)
    // ─────────────────────────────────────────────────────────
    Route::get('/mfa/setup', [\App\Http\Controllers\Admin\MfaController::class, 'setup'])->name('admin.mfa.setup');
    Route::post('/mfa/initiate', [\App\Http\Controllers\Admin\MfaController::class, 'initiate'])->name('admin.mfa.initiate');
    Route::post('/mfa/verify', [\App\Http\Controllers\Admin\MfaController::class, 'verify'])->name('admin.mfa.verify');
    Route::post('/mfa/disable', [\App\Http\Controllers\Admin\MfaController::class, 'disable'])->name('admin.mfa.disable');
    Route::post('/mfa/cancel-setup', [\App\Http\Controllers\Admin\MfaController::class, 'cancelSetup'])->name('admin.mfa.cancel-setup');
    Route::post('/mfa/regenerate', [\App\Http\Controllers\Admin\MfaController::class, 'regenerate'])->name('admin.mfa.regenerate');

    // ─────────────────────────────────────────────────────────
    //  Backup & Restore UI (Task 5)
    // ─────────────────────────────────────────────────────────
    Route::get('/backup', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('admin.backup.index');
    Route::post('/backup/export', [\App\Http\Controllers\Admin\BackupController::class, 'export'])->name('admin.backup.export');
    Route::post('/backup/import', [\App\Http\Controllers\Admin\BackupController::class, 'import'])->name('admin.backup.import');
    Route::get('/backup/download/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('admin.backup.download');
    Route::delete('/backup/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'destroy'])->name('admin.backup.delete');
});

// ───────────────────────────────────────────────────────────────
//  MFA Challenge Routes (Task 6) — outside 'auth' middleware
// ───────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/mfa/challenge', [\App\Http\Controllers\Auth\MfaChallengeController::class, 'showChallenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [\App\Http\Controllers\Auth\MfaChallengeController::class, 'verify'])->name('mfa.challenge.verify');
});
