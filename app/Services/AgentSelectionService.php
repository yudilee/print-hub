<?php

namespace App\Services;

use App\Models\PrintAgent;
use App\Models\PrintProfile;

class AgentSelectionService
{
    /**
     * Select the best available print agent based on priority chain.
     *
     * Priority:
     *   1. Explicit agent_id
     *   2. Profile's pinned agent (must be online)
     *   3. Any online agent in the given branch
     *   4. Any online agent globally
     *
     * @param int|null $agentId Explicit agent ID from request
     * @param PrintProfile|null $profile Resolved print profile (may have pinned agent)
     * @param int|null $branchId Branch to scope agent search
     * @param string|null $profileName For error messages
     * @return PrintAgent
     * @throws \RuntimeException When no online agent is available
     */
    public static function select(?int $agentId, ?PrintProfile $profile, ?int $branchId, ?string $profileName = null): PrintAgent
    {
        $agent = null;

        // 1. Explicit agent_id from request
        if ($agentId) {
            $agent = PrintAgent::where('id', $agentId)->where('is_active', true)->first();
        }

        // 2. Profile's pinned agent
        if (!$agent && $profile && $profile->print_agent_id) {
            $pinnedAgent = $profile->agent;
            if ($pinnedAgent) {
                if (!$pinnedAgent->isOnline()) {
                    $name = $profileName ?? $profile->name;
                    throw new \RuntimeException("The Hub assigned to queue '{$name}' is offline.");
                }
                $agent = $pinnedAgent;
            }
        }

        // 3. Any online agent in branch
        if (!$agent && $branchId) {
            $agent = PrintAgent::where('is_active', true)
                ->where('branch_id', $branchId)
                ->get()
                ->first(fn(PrintAgent $a) => $a->isOnline());
        }

        // 4. Any online agent globally
        if (!$agent) {
            $agent = PrintAgent::where('is_active', true)
                ->get()
                ->first(fn(PrintAgent $a) => $a->isOnline());
        }

        if (!$agent) {
            throw new \RuntimeException('No online agent available.');
        }

        return $agent;
    }
}
