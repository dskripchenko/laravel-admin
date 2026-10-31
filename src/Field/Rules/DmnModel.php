<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Rules;

/**
 * The value is a DMN 1.x file: XML whose root is `definitions` in a DMN model
 * namespace (1.1 to 1.5 — the test @dskripchenko/bpmn reads files by),
 * parsed safely and no larger than the cap (2 MB by default). See XmlModel.
 *
 * An empty value passes: whether the field is required is up to `required`.
 *
 *     'rules_xml' => ['required', new DmnModel],
 */
final class DmnModel extends XmlModel
{
    /** The DMN model namespaces, as @dskripchenko/bpmn's isDmnModelNs tells them. */
    public const NAMESPACE_PATTERN = '~omg\.org/spec/DMN/\d{8}/(?:MODEL/?|dmn\.xsd)$~';

    protected function notation(): string
    {
        return 'DMN';
    }

    protected function acceptsNamespace(string $namespace): bool
    {
        return preg_match(self::NAMESPACE_PATTERN, $namespace) === 1;
    }
}
