<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * An action that runs as a delayed process.
 *
 * It is meant for the long operations: a bulk export, an import, recomputing
 * statistics, a mass mailout. The SPA receives a {process_uuid} and follows
 * the progress by polling /api/admin/delayed-processes/{uuid}.
 *
 * On the server the action must be whitelisted in AllowlistRegistrar as
 * `entity::method`, or the SPA cannot start it at all.
 */
final class AsyncAction extends Action
{
    public function type(): string
    {
        return 'async';
    }

    /**
     * The handler class's FQCN. It must be registered in AllowlistRegistrar
     * as an `entity` the async actions may use.
     *
     * @param  class-string  $entity
     */
    public function handler(string $entity, string $method): self
    {
        $this->attributes['handler'] = ['entity' => $entity, 'method' => $method];

        return $this;
    }

    /**
     * Passes extra parameters to the handler at start-up.
     *
     * @param  array<string, mixed>  $params
     */
    public function withParams(array $params): self
    {
        $this->attributes['params'] = $params;

        return $this;
    }

    /**
     * A webhook with the progress and the result is sent to this callback URL.
     */
    public function callback(string $url): self
    {
        $this->attributes['callback'] = $url;

        return $this;
    }

    /**
     * How often, in seconds, the SPA polls for the progress.
     */
    public function pollInterval(int $seconds): self
    {
        $this->attributes['pollInterval'] = max(1, $seconds);

        return $this;
    }
}
