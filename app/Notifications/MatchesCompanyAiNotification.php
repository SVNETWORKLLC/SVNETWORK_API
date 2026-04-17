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
    public $link2;
    public function __construct($project, $link = null, $link2 = null)
    {
        $this->project = $project;
        $this->link = $link;
        $this->link2 = $link2;
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
                    ->subject('You’ve received a new client lead - contact now - SVNetwork')
                    ->view('mail.company.matches-company-ai', [
                        'project' => $this->project,
                        'link' => $this->link,
                        'link2' => $this->link2,
                        'notifiable' => $notifiable,
                    ]);
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
