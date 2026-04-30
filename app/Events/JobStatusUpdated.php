<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $job;

    public function __construct(PrintJob $job)
    {
        $this->job = $job;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('print-jobs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->job->id,
            'job_id' => $this->job->job_id,
            'status' => $this->job->status,
            'agent_id' => $this->job->print_agent_id,
            'printer' => $this->job->printer_name,
            'error' => $this->job->error,
            'updated_at' => $this->job->updated_at->toIso8601String(),
        ];
    }
}
