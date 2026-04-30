<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action;

/**
 * Внешняя или внутренняя ссылка.
 */
final class Link extends Action
{
    public function type(): string
    {
        return 'link';
    }

    public function href(string $href): self
    {
        $this->attributes['href'] = $href;

        return $this;
    }

    /**
     * @param  '_self'|'_blank'|'_parent'|'_top'  $target
     */
    public function target(string $target): self
    {
        $this->attributes['target'] = $target;

        return $this;
    }
}
