<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Status;

/**
 * A short state a plugin wants shown in the panel's top bar.
 *
 * The case it exists for: the health pack computes whether the installation is
 * alive, and until now had nowhere to say so — the answer lived in a section
 * one had to open on purpose, which is the opposite of what a health check is
 * for. Anything of that shape fits: a queue that stopped, a licence about to
 * expire, an import still running.
 *
 * The panel owns the drawing, the plugin owns the answer. That division is the
 * point: a package must not have to ship Vue components and a build of its own
 * to put a dot in the header.
 */
interface StatusIndicator
{
    /** A stable identifier, `admin.health` and the like. */
    public function key(): string;

    /**
     * What to show. `label` is a word or two — it is drawn next to the dot on a
     * wide screen and hidden on a narrow one; `detail` is the tooltip; `url` is
     * where a click leads, when there is somewhere to go.
     *
     * @return array{status: 'ok'|'warning'|'error'|'unknown', label: string, detail?: string, url?: string}
     */
    public function state(): array;
}
