<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * General-purpose notification. Drop this on any Notifiable model from
 * anywhere in the app, e.g.:
 *
 *   $user->notify(new SystemNotification('New Article', 'Check it out!', url: route('news.show', $article)));
 *
 * For bulk sends, use the Notification facade instead of looping ->notify():
 *
 *   Notification::send($users, new SystemNotification(...));
 *
 * A notification is clickable in one of two ways: give it a `url` to send the
 * user somewhere, or a `body` to open an in-app detail view with more text.
 * If both are set, `url` wins.
 */
class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $icon = 'fa-bell',
        public ?string $sender = null,
        public ?string $body = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
            'sender' => $this->sender,
            'body' => $this->body,
        ];
    }
}
