<?php

namespace App\Events;

use App\Models\PrintAgent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;

    /**
     * Create a new event instance.
     */
    public function __construct(PrintAgent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('agent.' . $this->agent->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'agent.status.updated';
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->agent->id,
            'name'       => $this->agent->name,
            'is_online'  => $this->agent->isOnline(),
            'printers'   => $this->agent->printers,
            'location'   => $this->agent->location,
            'department' => $this->agent->department,
            'last_seen_at' => $this->agent->last_seen_at?->toIso8601String(),
            'updated_at' => $this->agent->updated_at->toIso8601String(),
        ];
    }
}
