<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoMatchesAdminNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $data;
    protected $title;
    protected $body;

    public function __construct($data)
    {
        $this->data = $data;
        if(isset($data['service'])){
            $this->title = 'No matches for '. $data['service']->name;
            $this->body = 'No results found for the service '.$data['service']->name.' in the state of '. $data['zipcode']->state;
        } else {
            $this->title = 'No matches for custom search';
            $this->body = 'No results found for a custom search: '. $data['description'] .' in the state of '. $data['zipcode']->state;
        }
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
        ->subject($this->title)
                    ->line('Alerta:')
                    ->line($this->body)
                    ->action('Show Dashboard', config('app.app_url').'/admin/matches');
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
