<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Fixtures\Host;

use Dskripchenko\LaravelAdmin\Http\AdminApiModule;

/**
 * A host module the way the docblock of AdminApiModule invites one to be
 * written: the admin and the panels from the parent, a version of its own
 * next to them.
 */
final class TestHostApiModule extends AdminApiModule
{
    public function getApiVersionList(): array
    {
        return [
            ...parent::getApiVersionList(),
            'v1' => TestHostApi::class,
        ];
    }
}
