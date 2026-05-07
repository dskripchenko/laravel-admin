<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

/**
 * Статический Markdown-блок для onboarding'а / описания / changelog'а.
 *
 * Контент задаётся либо строкой, либо callable (для динамической генерации).
 * SPA рендерит markdown в HTML.
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
