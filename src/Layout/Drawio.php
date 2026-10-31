<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

/**
 * A draw.io (diagrams.net) file — an `.drawio` or mxfile document — drawn as
 * SVG by @dskripchenko/drawio in a scrollable box.
 *
 *     Layout::drawio('network')                   // $state['network']
 *         ->page('Rack B')                        // a page by its index, id or name
 *     Layout::drawio('plan')->url('/files/plan.drawio')
 */
final class Drawio extends DocumentLayout
{
    public function type(): string
    {
        return 'drawio';
    }

    /** Which page: its index, id or name. The first by default. */
    public function page(int|string $page): self
    {
        $this->props['page'] = $page;

        return $this;
    }
}
