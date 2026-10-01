<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

/**
 * The default panel mounted at the root of its own host:
 * ADMIN_DOMAIN=admin.example.test, ADMIN_PATH=''.
 */
abstract class RootPanelTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('admin.path', '');
        $app['config']->set('admin.domain', 'admin.example.test');
    }
}
