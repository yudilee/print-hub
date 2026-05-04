<?php

use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Job status updates — broadcast to users monitoring a specific job.
 * Authorized if user is a super-admin, admin, or the job's branch manager.
 */
Broadcast::channel('job.{jobId}', function ($user, $jobId) {
    $job = PrintJob::where('job_id', $jobId)->first();
    if (! $job) {
        return false;
    }
    // Super-admins and admins can see all jobs
    if ($user->hasAnyRole(['super-admin', 'admin'])) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    // Branch-level access
    if ($job->branch_id && $user->branch_id === $job->branch_id) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

/**
 * Agent status updates — broadcast when an agent comes online/offline.
 */
Broadcast::channel('agent.{agentId}', function ($user, $agentId) {
    $agent = PrintAgent::find($agentId);
    if (! $agent) {
        return false;
    }
    if ($user->hasAnyRole(['super-admin', 'admin'])) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    if ($agent->branch_id && $user->branch_id === $agent->branch_id) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

/**
 * Admin queue channel — broadcast queue changes to admin UI.
 */
Broadcast::channel('admin.queue', function ($user) {
    return $user->hasAnyRole(['super-admin', 'admin']);
});
