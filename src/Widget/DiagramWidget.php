<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use Dskripchenko\LaravelAdmin\Layout\Diagram;
use InvalidArgumentException;

/**
 * A diagram on a dashboard, drawn from text by @dskripchenko/diagrams the way
 * Layout::diagram() draws it: Mermaid, PlantUML, Graphviz DOT, D2, DBML,
 * Structurizr or a Wardley map.
 *
 *     DiagramWidget::make()->title('Order flow')
 *         ->content("flowchart LR\n  new --> paid --> shipped");
 *
 *     DiagramWidget::make()->content(fn () => Schema::asDbml())->syntax('dbml');
 *
 * Without syntax() the package tells the notation from the source's start. A
 * diagram larger than the widget scrolls inside it.
 */
class DiagramWidget extends Widget
{
    protected ?int $rowSpan = 3;

    /** @var string|(callable(): string) */
    private $content = '';

    private ?string $syntax = null;

    public function widgetType(): string
    {
        return 'diagram';
    }

    /**
     * The diagram's source: a string or a callable returning one.
     *
     * @param  string|callable(): string  $content
     */
    public function content(string|callable $content): static
    {
        $this->content = $content;

        return $this;
    }

    /** The source's notation: one of Layout\Diagram::SYNTAXES. */
    public function syntax(string $syntax): static
    {
        if (! in_array($syntax, Diagram::SYNTAXES, true)) {
            throw new InvalidArgumentException("Unknown diagram syntax `{$syntax}`: ".implode(', ', Diagram::SYNTAXES));
        }
        $this->syntax = $syntax;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $content = is_callable($this->content) ? ($this->content)() : $this->content;

        return [
            'content' => (string) $content,
            'syntax' => $this->syntax,
        ];
    }
}
