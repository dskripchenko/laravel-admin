<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Http\Controllers;

use Dskripchenko\LaravelAdminJobs\Services\JobOperations;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Custom routes для cancel/retry-failed по batch'ам Bus::batch().
 */
final class JobBatchController extends ApiController
{
    public function __construct(private readonly JobOperations $ops) {}

    /**
     * @input string $id Batch UUID.
     *
     * @output object $payload
     * @output bool $payload.cancelled
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string']]);
        $ok = $this->ops->cancelBatch($data['id']);

        return $this->success(['cancelled' => $ok]);
    }

    /**
     * @input string $id
     *
     * @output object $payload
     * @output bool $payload.dispatched
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'string']]);
        $ok = $this->ops->retryBatchFailures($data['id']);

        return $this->success(['dispatched' => $ok]);
    }
}
