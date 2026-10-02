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
 * The title and the body are stored as source strings and translated when
 * they are read, into the reader's locale (see NotificationController). The
 * variable parts go into `params`, so the stored text stays a translation key:
 *
 *     new AdminNotification(
 *         title: 'Импорт завершён',
 *         body: 'Импортировано записей: :count',
 *         params: ['count' => 1234],
 *     );
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
        /** @var array<string, scalar|null> `:name` placeholders of the title and the body */
        public readonly array $params = [],
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
        $data = [
            'title' => $this->title,
            'body' => $this->body,
            'level' => $this->level,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
        // `??`: a notification queued by a version without params comes back
        // from the queue with the property uninitialized. Stored only when
        // given, so a notification without them keeps its old shape.
        $params = $this->params ?? []; // @phpstan-ignore nullCoalesce.property
        if ($params !== []) {
            $data['params'] = $params;
        }

        return $data;
    }
}
