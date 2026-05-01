<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminJobs\Http\Controllers;

use Dskripchenko\LaravelAdminJobs\Services\JobOperations;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Custom routes для retry/forget failed-jobs.
 *
 * Endpoint'ы регистрируются в AdminJobsServiceProvider::boot() и
 * прикрыты `AdminAccess::class.':admin.system.jobs.failed.{action}'`
 * middleware'ом.
 */
final class FailedJobController extends ApiController
{
    public function __construct(private readonly JobOperations $ops) {}

    /**
     * @input string $uuid UUID failed-job'а.
     *
     * @output object $payload
     * @output bool $payload.success
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function retry(Request $request): JsonResponse
    {
        $data = $request->validate(['uuid' => ['required', 'string']]);
        $ok = $this->ops->retryFailedJob($data['uuid']);

        return $this->success(['success' => $ok]);
    }

    /**
     * @input string $uuid
     *
     * @output object $payload
     * @output bool $payload.deleted
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function forget(Request $request): JsonResponse
    {
        $data = $request->validate(['uuid' => ['required', 'string']]);
        $ok = $this->ops->forgetFailedJob($data['uuid']);

        return $this->success(['deleted' => $ok]);
    }

    /**
     * @input array $uuids
     *
     * @output object $payload
     * @output int $payload.count
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function retryBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuids' => ['required', 'array', 'min:1'],
            'uuids.*' => ['string'],
        ]);
        $count = $this->ops->retryFailedJobs($data['uuids']);

        return $this->success(['count' => $count]);
    }

    /**
     * @input array $uuids
     *
     * @output object $payload
     * @output int $payload.count
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     */
    public function forgetBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuids' => ['required', 'array', 'min:1'],
            'uuids.*' => ['string'],
        ]);
        $count = $this->ops->forgetFailedJobs($data['uuids']);

        return $this->success(['count' => $count]);
    }
}
