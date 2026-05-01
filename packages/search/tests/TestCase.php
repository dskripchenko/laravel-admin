<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminSearch\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminSearch\AdminSearchServiceProvider;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminSearchServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_search_users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }
}
