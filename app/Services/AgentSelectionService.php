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
    public static function select(?int $agentId, ?PrintProfile $profile, ?int $branchId, ?string $profileName = null, ?string $printerName = null): PrintAgent
    {
        $agent = null;

        // 1. Explicit agent_id from request (highest priority)
        if ($agentId) {
            $agent = PrintAgent::where('id', $agentId)->where('is_active', true)->first();
            if ($agent && !$agent->isOnline()) {
                throw new \RuntimeException("Selected agent '{$agent->name}' is currently OFFLINE.");
            }
        }

        // 2. If a specific printer is targeted, verify which agent actually hosts this printer
        $targetPrinter = $printerName ?: ($profile ? $profile->default_printer : null);
        if (!$agent && $targetPrinter) {
            $agents = PrintAgent::where('is_active', true)->get();
            $ownerAgent = $agents->first(function (PrintAgent $a) use ($targetPrinter) {
                return in_array($targetPrinter, $a->printers ?? [], true);
            });

            if ($ownerAgent) {
                if (!$ownerAgent->isOnline()) {
                    throw new \RuntimeException("Target printer '{$targetPrinter}' is hosted on workstation '{$ownerAgent->name}', which is currently OFFLINE.");
                }
                $agent = $ownerAgent;
            }
        }

        // 3. Profile's pinned agent — only if no explicit agent/printer owner was resolved
        if (!$agent && $profile && $profile->print_agent_id) {
            $pinnedAgent = $profile->agent;
            if ($pinnedAgent && $pinnedAgent->isOnline()) {
                $agent = $pinnedAgent;
            }
        }

        // 4. Any online agent in the same branch
        if (!$agent && $branchId) {
            $agent = PrintAgent::where('is_active', true)
                ->where('branch_id', $branchId)
                ->get()
                ->first(fn(PrintAgent $a) => $a->isOnline());
        }

        // 5. Any online agent globally (last resort, only if no specific printer was targeted)
        if (!$agent && !$targetPrinter) {
            $agent = PrintAgent::where('is_active', true)
                ->get()
                ->first(fn(PrintAgent $a) => $a->isOnline());
        }

        if (!$agent) {
            if ($targetPrinter) {
                throw new \RuntimeException("Printer '{$targetPrinter}' is not available on any online print agent.");
            }
            throw new \RuntimeException('No online agent available.');
        }

        return $agent;
    }
}
