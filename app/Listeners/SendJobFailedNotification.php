<?php

namespace App\Listeners;

use App\Events\JobStatusUpdated;
use App\Models\User;
use App\Notifications\JobFailed;
use Illuminate\Support\Facades\Notification;

class SendJobFailedNotification
{
    /**
     * Handle the event.
     */
    public function handle(JobStatusUpdated $event): void
    {
        $job = $event->job;

        // Only send notification when a job fails
        if ($job->status !== 'failed') {
            return;
        }

        // Notify super-admins and company-admins
        $admins = User::whereIn('role', ['super-admin', 'company-admin'])->get();

        Notification::send($admins, new JobFailed($job, $job->error));
    }
}
