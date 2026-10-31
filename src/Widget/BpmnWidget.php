<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Widget;

use InvalidArgumentException;

/**
 * A BPMN process or a DMN decision model on a dashboard, shown read-only by
 * the viewers of @dskripchenko/bpmn — pan and zoom; in DMN a double click on
 * a decision opens its table.
 *
 *     BpmnWidget::make()->title('Order process')
 *         ->xml(fn () => Storage::get('processes/order.bpmn'))
 *         ->highlight(['Task_approve']);
 *
 *     BpmnWidget::make()->title('Discounts')->dmn()->url('/files/discount.dmn');
 *
 * url() loads the file in the browser, from an address of the panel's own
 * site, with the user's session.
 */
class BpmnWidget extends Widget
{
    protected ?int $rowSpan = 3;

    private string $notation = 'bpmn';

    /** @var string|(callable(): string)|null */
    private $xml = null;

    private ?string $url = null;

    /** @var list<string> */
    private array $highlight = [];

    private ?string $decision = null;

    private string|int|null $diagram = null;

    public function widgetType(): string
    {
        return 'bpmn';
    }

    /** The model's notation: `bpmn` (the default) or `dmn`. */
    public function notation(string $notation): static
    {
        if (! in_array($notation, ['bpmn', 'dmn'], true)) {
            throw new InvalidArgumentException("BpmnWidget::notation() takes bpmn or dmn, `{$notation}` given");
        }
        $this->notation = $notation;

        return $this;
    }

    /** A DMN decision model rather than a BPMN process. */
    public function dmn(): static
    {
        return $this->notation('dmn');
    }

    /**
     * The model's XML: a string or a callable returning one.
     *
     * @param  string|callable(): string  $xml
     */
    public function xml(string|callable $xml): static
    {
        $this->xml = $xml;
        $this->url = null;

        return $this;
    }

    /**
     * Loads the XML in the browser from this address — a path of the panel's
     * site (`/files/order.bpmn`) or a URL of the same origin.
     */
    public function url(string $url): static
    {
        $url = trim($url);
        $sameSitePath = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        if (! $sameSitePath && preg_match('#^https?://[^/\s]+#i', $url) !== 1) {
            throw new InvalidArgumentException("BpmnWidget::url() takes a path or an http(s) URL, `{$url}` given");
        }
        $this->url = $url;
        $this->xml = null;

        return $this;
    }

    /**
     * The ids of the elements to outline.
     *
     * @param  list<string>  $ids
     */
    public function highlight(array $ids): static
    {
        $this->highlight = array_map(strval(...), $ids);

        return $this;
    }

    /** The DMN decision whose table is opened first. */
    public function decision(string $id): static
    {
        $this->decision = $id;

        return $this;
    }

    /** Which diagram of a BPMN file that has several: its id or index. */
    public function showDiagram(string|int $diagram): static
    {
        $this->diagram = $diagram;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $xml = is_callable($this->xml) ? ($this->xml)() : $this->xml;

        return [
            'notation' => $this->notation,
            'content' => $this->url === null ? (string) ($xml ?? '') : null,
            'url' => $this->url,
            'highlight' => $this->highlight,
            'decision' => $this->decision,
            'diagram' => $this->diagram,
        ];
    }
}
