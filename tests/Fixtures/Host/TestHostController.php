<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tests\Fixtures\Host;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TestHostController extends ApiController
{
    /**
     * Ping
     *
     * @output string $pong Pong
     */
    public function show(Request $request): JsonResponse
    {
        return $this->success(['pong' => 'ok', 'session' => $request->hasSession()]);
    }

    /**
     * Store
     *
     * @input string $value Value
     */
    public function store(Request $request): JsonResponse
    {
        return $this->success(['value' => $request->input('value')]);
    }
}
