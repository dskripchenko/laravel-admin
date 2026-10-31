<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use InvalidArgumentException;

/**
 * A diagram drawn from text by @dskripchenko/diagrams: Mermaid, PlantUML,
 * Graphviz DOT, D2, DBML, Structurizr or a Wardley map.
 *
 *     Layout::diagram('schema')                       // $state['schema']
 *     Layout::diagram("@startuml\nA -> B\n@enduml")   // inline, PlantUML by its first line
 *     Layout::diagram('erd')->syntax('dbml')->height(480)
 *
 * Without syntax() the package tells the notation from the source's start.
 */
final class Diagram extends DocumentLayout
{
    /** The notations the package reads. */
    public const SYNTAXES = ['mermaid', 'plantuml', 'dot', 'd2', 'dbml', 'structurizr', 'wardley'];

    public function type(): string
    {
        return 'diagram';
    }

    /** The source's notation: one of SYNTAXES; detected from the source when not set. */
    public function syntax(string $syntax): self
    {
        if (! in_array($syntax, self::SYNTAXES, true)) {
            throw new InvalidArgumentException("Unknown diagram syntax `{$syntax}`: ".implode(', ', self::SYNTAXES));
        }
        $this->props['syntax'] = $syntax;

        return $this;
    }
}
