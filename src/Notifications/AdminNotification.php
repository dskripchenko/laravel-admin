<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * The base notification of the admin shell.
 *
 * It is sent through Laravel's usual notify() or the Notification facade:
 *
 *     $admin->notify(new AdminNotification(
 *         title: 'The import has finished',
 *         body: '1234 records imported',
 *         level: 'success',
 *         url: '/admin/resources/products',
 *     ));
 *
 * The levels are 'info', 'success', 'warning' and 'error'. The SPA draws the
 * notification with a colour and an icon to match, and the url opens a page
 * when clicked.
 *
 * It can be extended for domain-specific notifications, with their own
 * channels and via() configuration.
 */
class AdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const LEVELS = ['info', 'success', 'warning', 'error'];

    public function __construct(
        public readonly string $title,
        public readonly string $body = '',
        public readonly string $level = 'info',
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
    ) {
        if (! in_array($this->level, self::LEVELS, true)) {
            throw new InvalidArgumentException(
                'AdminNotification level must be one of: '.implode(', ', self::LEVELS),
            );
        }
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'level' => $this->level,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
