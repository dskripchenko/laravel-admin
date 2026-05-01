<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminHealth\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminHealth\AdminHealthServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminHealthServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        $app['config']->set('admin-health.checks', []);
    }
}
