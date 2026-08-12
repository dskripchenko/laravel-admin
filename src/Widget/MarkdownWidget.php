<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * A static markdown block, for onboarding notes, a description or a changelog.
 *
 * Its content is either a string or a callable, for generating it on the fly.
 * The SPA renders the markdown into HTML.
 */
class MarkdownWidget extends Widget
{
    /** @var string|(callable(): string) */
    private $content = '';

    public function widgetType(): string
    {
        return 'markdown';
    }

    /**
     * @param  string|callable(): string  $content
     */
    public function content(string|callable $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $resolved = is_callable($this->content) ? ($this->content)() : $this->content;

        return ['content' => $resolved];
    }
}
