<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field\Rules;

/**
 * The value is a BPMN 2.0 file: XML whose root is `definitions` in the BPMN
 * model namespace, parsed safely and no larger than the cap (2 MB by
 * default). See XmlModel.
 *
 * An empty value passes: whether the field is required is up to `required`.
 *
 *     'process_xml' => ['required', new BpmnModel],
 *     'process_xml' => [new BpmnModel(maxBytes: 512 * 1024)],
 */
final class BpmnModel extends XmlModel
{
    public const NAMESPACE = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

    protected function notation(): string
    {
        return 'BPMN';
    }

    protected function acceptsNamespace(string $namespace): bool
    {
        return $namespace === self::NAMESPACE;
    }
}
