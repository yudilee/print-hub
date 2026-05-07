<?php

namespace App\Notifications;

use App\Models\PrintAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentOffline extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PrintAgent $agent,
        public ?int $minutesOffline = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notify_email ?? $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $minutes = $this->minutesOffline ?? ($this->agent->last_seen_at?->diffInMinutes(now()) ?? 0);

        return (new MailMessage)
            ->subject('Print Agent Offline — ' . $this->agent->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A print agent has gone offline.')
            ->line('Agent: **' . $this->agent->name . '**')
            ->line('Location: ' . ($this->agent->location ?? 'N/A'))
            ->line('Department: ' . ($this->agent->department ?? 'N/A'))
            ->line('Last seen: ' . ($this->agent->last_seen_at?->diffForHumans() ?? 'Never'))
            ->line('Offline duration: ~' . $minutes . ' minutes')
            ->action('View Agent', url(route('admin.agents')))
            ->line('Please check the agent status and take necessary action.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $minutes = $this->minutesOffline ?? ($this->agent->last_seen_at?->diffInMinutes(now()) ?? 0);

        return [
            'title'   => 'Agent Offline',
            'message' => 'Agent "' . $this->agent->name . '" has been offline for ~' . $minutes . ' minutes.',
            'agent_id' => $this->agent->id,
            'type'    => 'agent_offline',
        ];
    }
}
