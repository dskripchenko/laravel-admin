<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Tests;

use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminJobs\AdminJobsServiceProvider;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends PackageTestCase
{
    protected function additionalProviders(): array
    {
        return [AdminJobsServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        $app['config']->set('queue.default', 'database');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('failed_jobs', function ($table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('job_batches', function ($table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }
}
