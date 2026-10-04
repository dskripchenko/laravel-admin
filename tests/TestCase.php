<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Dskripchenko\DelayedProcess\Providers\DelayedProcessServiceProvider;
use Dskripchenko\LaravelAdmin\AdminServiceProvider;
use Dskripchenko\LaravelApi\Providers\ApiServiceProvider;
use Dskripchenko\LaravelTranslatable\Providers\TranslatableServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** The per-process skeleton copy, made once per worker. */
    private static ?string $isolatedBasePath = null;

    /**
     * Every test runs on Testbench's skeleton app, and several of them write
     * into it: admin:install publishes config/admin.php and the migrations,
     * admin:publish fills public/vendor/admin, the shared-strategy install
     * adds a migration to database/migrations. Under `pest --parallel` all
     * workers share that one directory, so a worker would load another one's
     * published config, or migrate a file that is deleted mid-listing. Each
     * parallel worker gets a private copy of the skeleton instead.
     */
    public static function applicationBasePath()
    {
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN');
        if ($token === false || $token === '' || $token === null) {
            return parent::applicationBasePath();
        }

        if (self::$isolatedBasePath === null) {
            $skeleton = (string) \Orchestra\Testbench\default_skeleton_path();
            $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-admin-testbench-'
                .substr(md5($skeleton), 0, 8).'-'.$token;
            // A fresh copy per worker start, so leftovers of an aborted run do not leak in.
            $files = new Filesystem;
            $files->deleteDirectory($target);
            $files->ensureDirectoryExists($target);
            foreach (scandir($skeleton) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $from = $skeleton.DIRECTORY_SEPARATOR.$entry;
                $to = $target.DIRECTORY_SEPARATOR.$entry;
                if (is_link($from)) {
                    // Testbench links laravel/vendor to the package's vendor: link it
                    // again rather than copy the whole vendor tree (and itself).
                    symlink((string) realpath($from), $to);
                } elseif (is_dir($from)) {
                    $files->copyDirectory($from, $to);
                } else {
                    $files->copy($from, $to);
                }
            }
            self::$isolatedBasePath = $target;
        }

        return self::$isolatedBasePath;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The login/2FA routes carry a `throttle` middleware (limit 5/min per
        // IP). Its rate-limiter state bleeds across tests in a single process
        // and trips spurious 429s under random ordering. No test exercises
        // throttling itself, so disable that one middleware for the suite.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApiServiceProvider::class,
            DelayedProcessServiceProvider::class,
            TranslatableServiceProvider::class,
            AdminServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
