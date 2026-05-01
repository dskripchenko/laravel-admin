<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminStarter\AdminStarterServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminStarterServiceProvider::class];
    }
}
