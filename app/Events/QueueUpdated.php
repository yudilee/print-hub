<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $queueData;

    /**
     * Create a new event instance.
     *
     * @param array $queueData Summary of the current queue state.
     */
    public function __construct(array $queueData)
    {
        $this->queueData = $queueData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin.queue'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'total_pending'  => $this->queueData['total_pending'] ?? 0,
            'total_processing' => $this->queueData['total_processing'] ?? 0,
            'total_queued'   => $this->queueData['total_queued'] ?? 0,
            'latest_job'     => $this->queueData['latest_job'] ?? null,
            'updated_at'     => now()->toIso8601String(),
        ];
    }
}
