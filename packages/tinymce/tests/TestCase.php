<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminTinymce\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminTinymce\AdminTinymceServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminTinymceServiceProvider::class];
    }
}
