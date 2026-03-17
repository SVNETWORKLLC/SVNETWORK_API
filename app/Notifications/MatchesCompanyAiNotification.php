<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchesCompanyAiNotification extends Notification
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
                    ->subject('You have a new Client')
                    ->line("You have received a new match from SVNETWORK.")
                    ->line("Client's name: ".$this->project->user->name)
                    ->line("Client's email: ".$this->project->user->email)
                    ->line("Client's phone: ".$this->project->user->phone)
                    ->line("Project: ".$this->project->description)
                    ->action('Claim your company', $this->link)
                    ->line('If you want to attract more clients, claim your company profile and increase your visibility.');
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
