<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Fixtures\Host;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Host API v1
 * A public, stateless API of the host application
 */
final class TestHostApi extends BaseApi
{
    public static function getMethods(): array
    {
        return [
            'middleware' => [TestHostMarkerMiddleware::class],
            'controllers' => [
                'ping' => [
                    'controller' => TestHostController::class,
                    'actions' => [
                        'show' => ['method' => 'get'],
                        'store' => ['method' => 'post'],
                    ],
                ],
            ],
        ];
    }
}
