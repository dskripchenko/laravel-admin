<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
        ];
    }
}
