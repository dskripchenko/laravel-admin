<?php

declare(strict_types=1);

/**
 * The formatted resource with an export of raw values.
 *
 * @internal
 */
final class TestRawExportResource extends TestFormattedResource
{
    public function exportsFormatted(): bool
    {
        return false;
    }
}
