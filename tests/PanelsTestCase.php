<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An environment with TWO panels: the default `admin` (the legacy config) plus
 * `client` at the site's root with a guard, an API and a plugin of its own — the
 * config is set BEFORE the providers boot, so the guards, the routes and the API
 * versions come up the regular way.
 */
abstract class PanelsTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('admin.plugins', [\TestSamplePlugin::class]);
        $app['config']->set('admin.panels', [
            'client' => [
                'path' => '',
                'exclude_prefixes' => ['api', 'admin'],
                'auth' => [
                    'strategy' => 'dedicated',
                    'guard' => 'client',
                    'provider' => 'test_client_users',
                    'model' => \TestPanelClientUser::class,
                    'table' => 'test_client_panel_users',
                    'password_broker' => 'test_client_users',
                ],
                'api' => \TestPanelClientApi::class,
                'middleware' => [
                    'shell' => [
                        'web',
                        \Dskripchenko\LaravelAdmin\Http\Middleware\AdminLocale::class,
                        \Dskripchenko\LaravelAdmin\Http\Middleware\AdminCspNonce::class,
                    ],
                    // No api extras are needed: the base admin.middleware.api stack is shared.
                    'api' => [],
                ],
                'plugins' => [\TestPanelClientPlugin::class],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_client_panel_users')) {
            Schema::create('test_client_panel_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                // The panels' user models are disabled through `enabled` (for
                // AdminUser that is `is_active`) — both fields lock the door.
                $table->boolean('enabled')->default(true);
                // The state of the account's owner — for the isDisabledForLogin hook.
                $table->boolean('owner_suspended')->default(false);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('test_panel_projects')) {
            Schema::create('test_panel_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        \TestPanelClientApi::clearCache();
        \Dskripchenko\LaravelAdmin\Http\AdminApi::clearCache();
    }
}
