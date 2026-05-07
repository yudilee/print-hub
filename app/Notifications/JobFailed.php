<?php

namespace App\Notifications;

use App\Models\PrintJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PrintJob $job,
        public string $errorMessage = '',
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
        return (new MailMessage)
            ->subject('Print Job Failed — ' . ($this->job->job_id))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A print job has failed.')
            ->line('Job ID: **' . $this->job->job_id . '**')
            ->line('Template: ' . ($this->job->template_name ?? 'N/A'))
            ->line('Agent: ' . ($this->job->agent->name ?? 'N/A'))
            ->line('Error: ' . ($this->errorMessage ?: ($this->job->error ?? 'Unknown error')))
            ->action('View Job', url(route('admin.jobs', ['job_id' => $this->job->job_id])))
            ->line('Please review the job details and retry if necessary.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Print Job Failed',
            'message' => 'Job ' . $this->job->job_id . ' failed: ' . ($this->errorMessage ?: ($this->job->error ?? 'Unknown error')),
            'job_id'  => $this->job->job_id,
            'type'    => 'job_failed',
        ];
    }
}
