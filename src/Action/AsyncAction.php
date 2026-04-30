<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Action, выполняющийся как отложенный процесс (delayed-process).
 *
 * Используется для долгих операций: bulk-export, импорт, recompute statistics,
 * массовый mailout. SPA получает {process_uuid} и слушает прогресс через
 * polling /api/admin/delayed-processes/{uuid}.
 *
 * Серверная сторона: action заведомо whitelisted в AllowlistRegistrar
 * (`entity::method` — иначе SPA не сможет его инициировать).
 */
final class AsyncAction extends Action
{
    public function type(): string
    {
        return 'async';
    }

    /**
     * FQCN handler-класса. Должен быть зарегистрирован в AllowlistRegistrar
     * как разрешённое `entity` для async-actions.
     *
     * @param  class-string  $entity
     */
    public function handler(string $entity, string $method): self
    {
        $this->attributes['handler'] = ['entity' => $entity, 'method' => $method];

        return $this;
    }

    /**
     * Передать дополнительные параметры handler'у при запуске.
     *
     * @param  array<string, mixed>  $params
     */
    public function withParams(array $params): self
    {
        $this->attributes['params'] = $params;

        return $this;
    }

    /**
     * URL'у callback'а отправляется webhook с progress/result.
     */
    public function callback(string $url): self
    {
        $this->attributes['callback'] = $url;

        return $this;
    }

    /**
     * SPA polling-интервал в секундах для отслеживания progress.
     */
    public function pollInterval(int $seconds): self
    {
        $this->attributes['pollInterval'] = max(1, $seconds);

        return $this;
    }
}
