<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Окружение с ДВУМЯ панелями: дефолтная `admin` (легаси-конфиг) + `client`
 * на корне сайта со своим guard'ом/API/плагином — конфиг задаётся ДО boot'а
 * провайдеров, поэтому guards/роуты/API-версии поднимаются штатным путём.
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
                    // api-extras не нужны: базовый стек admin.middleware.api общий.
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
