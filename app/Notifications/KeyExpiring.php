<?php

namespace App\Notifications;

use App\Models\PrintAgent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyExpiring extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PrintAgent $agent,
        public Carbon $expiresAt,
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
        $daysLeft = now()->diffInDays($this->expiresAt, false);

        return (new MailMessage)
            ->subject('API Key Expiring Soon — ' . $this->agent->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('The API key for agent "' . $this->agent->name . '" is expiring soon.')
            ->line('Agent: **' . $this->agent->name . '**')
            ->line('Expires: ' . $this->expiresAt->format('d M Y H:i'))
            ->line('Days remaining: ' . max(0, (int) $daysLeft))
            ->action('Rotate Key', url(route('admin.agents')))
            ->line('Please rotate the key before it expires to avoid service disruption.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $daysLeft = now()->diffInDays($this->expiresAt, false);

        return [
            'title'   => 'API Key Expiring',
            'message' => 'API key for "' . $this->agent->name . '" expires in ' . max(0, (int) $daysLeft) . ' days (' . $this->expiresAt->format('d M Y') . ').',
            'agent_id' => $this->agent->id,
            'type'    => 'key_expiring',
        ];
    }
}
