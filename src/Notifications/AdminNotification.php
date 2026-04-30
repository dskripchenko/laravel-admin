<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * Базовая нотификация для admin-shell'а.
 *
 * Отправляется через стандартный Laravel notify() / Notification facade:
 *
 *     $admin->notify(new AdminNotification(
 *         title: 'Импорт завершён',
 *         body: 'Импортировано 1234 записей',
 *         level: 'success',
 *         url: '/admin/resources/products',
 *     ));
 *
 * Поддерживаемые levels: 'info' | 'success' | 'warning' | 'error'.
 * SPA рендерит нотификацию с цветом + иконкой соответственно level'у;
 * url открывает страницу при клике.
 *
 * Можно extend'ить для domain-specific notifications с собственным channels
 * и via() конфигом.
 */
class AdminNotification extends Notification
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
