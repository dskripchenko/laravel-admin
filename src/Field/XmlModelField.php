<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Dskripchenko\LaravelAdmin\Field\Rules\XmlModel;
use InvalidArgumentException;

/**
 * What the BPMN and DMN editor fields share: the value is the model file's
 * XML, kept in a text column, checked on the server by the notation's rule
 * (added by the rules exporter) with a size cap.
 *
 * @method $this readonly(bool $readonly = true)
 */
abstract class XmlModelField extends Field
{
    /** The editor's height: 560 (px) or '70vh'; 560px by default. */
    public function height(int|string $height): static
    {
        $this->attributes['height'] = is_int($height) ? $height.'px' : $height;

        return $this;
    }

    /**
     * The largest file the server accepts, in bytes; 2 MB by default. Keep
     * it within the column: a TEXT column holds 64 KB, MEDIUMTEXT 16 MB.
     */
    public function maxSize(int $bytes): static
    {
        if ($bytes <= 0) {
            throw new InvalidArgumentException("{$this->fieldType()}->maxSize() must be positive, {$bytes} given");
        }
        $this->attributes['maxSize'] = $bytes;

        return $this;
    }

    /** The size cap in bytes. */
    protected function maxBytes(): int
    {
        $max = $this->attributes['maxSize'] ?? null;

        return is_int($max) ? $max : XmlModel::DEFAULT_MAX_BYTES;
    }

    /** The rule that checks a submitted file. */
    abstract public function modelRule(): XmlModel;
}
