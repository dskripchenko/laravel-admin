<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminQuill\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminQuill\AdminQuillServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminQuillServiceProvider::class];
    }
}
