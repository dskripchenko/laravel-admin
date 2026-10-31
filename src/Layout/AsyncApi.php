<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * An AsyncAPI document, YAML or JSON, as the reference of the openapi
 * package (@dskripchenko/openapi): servers, channels, operations and
 * messages with their payloads.
 *
 *     Layout::asyncapi('events_spec')             // $state['events_spec']
 *     Layout::asyncapi('spec')->url('/docs/asyncapi.yaml')
 */
final class AsyncApi extends DocumentLayout
{
    public function type(): string
    {
        return 'asyncapi';
    }
}
