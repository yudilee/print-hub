<?php

namespace App\Traits;

use App\Models\ActivityLog;

/**
 * Provides logActivity() method for controllers.
 */
trait LogsActivity
{
    protected function logActivity(string $action, $subject = null, array $properties = []): ActivityLog
    {
        return ActivityLog::record($action, $subject, $properties);
    }
}
