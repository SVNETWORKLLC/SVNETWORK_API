<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchesCompanyNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $project;
    public $link;
    public function __construct($project, $link = null)
    {
        $this->project = $project;
        $this->link = $link;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->greeting('Hello '. $notifiable->name)
                    ->subject('You have a new client for '.$this->project->service->name)
                    ->line("You have received a new match for the ".$this->project->service->name." service. To view the user's details, click here.")
                    ->action('View contact details', url($this->link))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
