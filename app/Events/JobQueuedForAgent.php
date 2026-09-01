<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobQueuedForAgent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PrintJob $job;

    public function __construct(PrintJob $job)
    {
        $this->job = $job;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('agent.' . $this->job->print_agent_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.queued';
    }

    public function broadcastWith(): array
    {
        return [
            'id'             => $this->job->id,
            'job_id'         => $this->job->job_id,
            'agent_id'       => $this->job->print_agent_id,
            'printer'        => $this->job->printer_name,
            'priority'       => $this->job->priority,
            'status'         => $this->job->status,
            'requires_approval' => (bool)$this->job->requires_approval,
            'created_at'     => $this->job->created_at?->toIso8601String(),
        ];
    }
}
